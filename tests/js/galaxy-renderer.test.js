import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { runInNewContext } from 'node:vm';
import * as THREE from 'three';
import * as data from '../../resources/js/galaxy-data.js';

const source = readFileSync(new URL('../../resources/js/galaxy-renderer.js', import.meta.url), 'utf8')
    .replace(/^import .*;\n/gm, '').replace('export function mountGalaxy', 'function mountGalaxy');
class Element extends EventTarget {
    style = {};
    value = '';
    clientWidth = 800;
    clientHeight = 500;
    children = [];
    setAttribute() {}
    getBoundingClientRect() { return { left: 0, top: 0, width: this.clientWidth, height: this.clientHeight }; }
    focus() { this.focused = true; }
    remove() { this.removed = true; }
    append(child) { this.children.push(child); }
    prepend(child) { this.children.unshift(child); }
    replaceChildren(...children) { this.children = children; }
}
function harness({ webgl = true, camera = true } = {}) {
    const nodes = new Map();
    const root = { querySelector(selector) {
        if (!nodes.has(selector)) nodes.set(selector, new Element());
        return nodes.get(selector);
    } };
    root.querySelector('[data-view]').value = 'nearby';
    root.querySelector('[data-radius]').value = '25';
    let renderer, controls, observer, frame, pickedCamera;
    let nextFrame = 0;
    const frames = new Map();
    class Renderer {
        constructor() {
            if (!webgl) throw new Error('WebGL unavailable');
            renderer = this;
            this.domElement = new Element();
            this.renders = 0;
        }
        setPixelRatio() {}
        setClearColor() {}
        setSize() { assert.equal(this.disposed, undefined); }
        render(scene, camera) {
            this.scene = scene;
            camera.updateMatrixWorld();
            this.cameraPosition = camera.position.toArray();
            this.cameraAspect = camera.aspect;
            this.renders++;
        }
        dispose() { this.disposed = true; }
        forceContextLoss() { this.contextLost = true; }
    }
    class Controls extends EventTarget {
        target = new THREE.Vector3();
        constructor(camera) { super(); controls = this; this.camera = camera; }
        listenToKeyEvents() {}
        update() { this.dispatchEvent(new Event('change')); }
        dispose() { this.disposed = true; }
    }
    const context = {
        ...data, AbortController, Event, URLSearchParams, Intl, Float32Array,
        THREE: { ...THREE, WebGLRenderer: Renderer, Raycaster: class extends THREE.Raycaster {
            setFromCamera(coords, camera) {
                pickedCamera = new THREE.Vector3().setFromMatrixPosition(camera.matrixWorld).toArray();
                super.setFromCamera(coords, camera);
            }
            intersectObject() { return []; }
        }, PerspectiveCamera: camera ? THREE.PerspectiveCamera : class { constructor() { throw new Error('Initialisation failed'); } } },
        OrbitControls: Controls,
        document: { documentElement: { lang: 'en-GB' }, createElement: () => new Element(), createDocumentFragment: () => new Element() },
        location: { search: '' }, window: { devicePixelRatio: 1 },
        requestAnimationFrame(callback) {
            frame = callback;
            const id = nextFrame++;
            frames.set(id, callback);
            return id;
        },
        cancelAnimationFrame(id) { frames.delete(id); },
        ResizeObserver: class {
            constructor(callback) { observer = this; this.callback = callback; }
            observe() {}
            disconnect() { this.disconnected = true; }
        },
    };
    runInNewContext(source, context);
    return { root, nodes, mount: context.mountGalaxy, get renderer() { return renderer; }, get controls() { return controls; }, get observer() { return observer; }, get frame() { return frame; }, get pendingFrames() { return frames.size; }, get pickedCamera() { return pickedCamera; },
        flushFrame() {
            const pending = [...frames.entries()];
            for (const [id, callback] of pending) { frames.delete(id); callback(); }
        } };
}
const host = { id: 'far', name: 'Distant system', planet_count: 1, distance_pc: 100, x_pc: 100, y_pc: 0, z_pc: 0,
    galactocentric_x_pc: -8022, galactocentric_y_pc: 0, galactocentric_z_pc: 20.8 };

test('selection still expands the distance filter, and disposal releases GPU resources and listeners', () => {
    const h = harness();
    const dispose = h.mount(h.root, [host], { focusOnReady: true });
    const picker = h.nodes.get('[data-system]');
    picker.value = 'far';
    picker.dispatchEvent(new Event('change'));
    assert.equal(h.nodes.get('[data-radius]').value, 'all');
    assert.equal(h.nodes.get('[data-selection]').children[0].textContent, 'Distant system');
    assert.equal(h.nodes.get('[data-selection]').children.at(-1).href, '/systems/far');
    assert.equal(h.renderer.domElement.focused, true);
    h.flushFrame();
    let geometries = 0, materials = 0;
    h.renderer.scene.traverse(object => {
        object.geometry?.addEventListener('dispose', () => { geometries++; });
        object.material?.addEventListener('dispose', () => { materials++; });
    });
    h.observer.callback();
    const renders = h.renderer.renders;
    dispose(); dispose();
    assert.equal(h.pendingFrames, 0);
    assert.equal(geometries, 4);
    assert.equal(materials, 4);
    assert.equal(h.renderer.disposed, true);
    assert.equal(h.renderer.contextLost, true);
    assert.equal(h.renderer.domElement.removed, true);
    assert.equal(h.controls.disposed, true);
    assert.equal(h.observer.disconnected, true);
    h.observer.callback(); // A queued observer notification must not touch a dead renderer.
    h.frame();
    assert.equal(h.renderer.renders, renders);
    h.nodes.get('[data-selection]').replaceChildren();
    picker.dispatchEvent(new Event('change'));
    assert.equal(h.nodes.get('[data-selection]').children.length, 0);
});

test('WebGL unavailable keeps textual selection and removes its listener on disposal', () => {
    const h = harness({ webgl: false });
    const dispose = h.mount(h.root, [host], { focusOnReady: true });
    const picker = h.nodes.get('[data-system]');
    assert.equal(picker.disabled, false);
    assert.equal(picker.focused, true);
    picker.value = 'far';
    picker.dispatchEvent(new Event('change'));
    assert.equal(h.nodes.get('[data-selection]').children[0].textContent, host.name);
    assert.match(h.nodes.get('[data-map-status]').textContent, /3D is unavailable/);
    dispose();
    h.nodes.get('[data-selection]').replaceChildren();
    picker.dispatchEvent(new Event('change'));
    assert.equal(h.nodes.get('[data-selection]').children.length, 0);
});

test('failure after creating WebGL cleans partial GPU state before retry', () => {
    const h = harness({ camera: false });
    assert.throws(() => h.mount(h.root, [host]), /Initialisation failed/);
    assert.equal(h.renderer.disposed, true);
    assert.equal(h.renderer.contextLost, true);
    assert.equal(h.renderer.domElement.removed, true);
});


test('successful and unavailable renderers do not take focus without continuation permission', () => {
    for (const webgl of [true, false]) {
        const h = harness({ webgl });
        const dispose = h.mount(h.root, [host], { focusOnReady: false });
        assert.notEqual(h.renderer?.domElement.focused, true);
        assert.notEqual(h.nodes.get('[data-system]').focused, true);
        dispose();
    }
});


test('initial setup and resize schedule one fresh render and no idle loop', () => {
    const h = harness();
    const dispose = h.mount(h.root, [host]);
    assert.equal(h.renderer.renders, 0);
    assert.equal(h.pendingFrames, 1); // Includes the valid RAF handle zero.
    const viewport = h.nodes.get('[data-viewport]');
    viewport.clientWidth = 900;
    h.observer.callback();
    viewport.clientWidth = 1100;
    h.observer.callback();
    assert.equal(h.pendingFrames, 1);
    h.flushFrame();
    assert.equal(h.renderer.renders, 1);
    assert.equal(h.renderer.cameraAspect, 1100 / 500);
    assert.equal(h.pendingFrames, 0);
    h.flushFrame();
    assert.equal(h.renderer.renders, 1);
    dispose();
});

test('control bursts, rebuilding, selection and reset draw only the latest state once per frame', () => {
    const h = harness();
    const dispose = h.mount(h.root, [host]);
    h.flushFrame();
    for (let i = 0; i < 20; i++) h.controls.update();
    const mode = h.nodes.get('[data-view]');
    mode.value = 'galaxy';
    mode.dispatchEvent(new Event('change'));
    h.nodes.get('[data-reset]').dispatchEvent(new Event('click'));
    const picker = h.nodes.get('[data-system]');
    picker.value = host.id;
    picker.dispatchEvent(new Event('change'));
    const latestPosition = h.controls.camera.position.toArray();
    assert.equal(h.renderer.renders, 1);
    assert.equal(h.pendingFrames, 1);
    assert.equal(h.nodes.get('[data-selection]').children[0].textContent, host.name);
    h.flushFrame();
    assert.equal(h.renderer.renders, 2);
    assert.deepEqual(h.renderer.cameraPosition, latestPosition);
    const marker = h.renderer.scene.children.find(object => object.material?.color.getHex() === 0xffffff);
    assert.equal(marker.visible, true);
    assert.deepEqual(marker.position.toArray(), data.position(host, 'galaxy'));
    assert.equal(h.pendingFrames, 0);
    h.nodes.get('[data-reset]').dispatchEvent(new Event('click'));
    assert.equal(h.pendingFrames, 1);
    h.flushFrame();
    assert.equal(h.renderer.renders, 3);
    dispose();
});

test('disposal cancels even the first frame and ignores a late controls notification', () => {
    const h = harness();
    const dispose = h.mount(h.root, [host]);
    const lateFrame = h.frame;
    dispose();
    assert.equal(h.pendingFrames, 0);
    h.controls.update();
    lateFrame();
    assert.equal(h.pendingFrames, 0);
    assert.equal(h.renderer.renders, 0);
});

test('picking before a pending paint uses the latest camera world position', () => {
    const h = harness();
    const dispose = h.mount(h.root, [host]);
    h.flushFrame();
    const picker = h.nodes.get('[data-system]');
    picker.value = host.id;
    picker.dispatchEvent(new Event('change'));
    const expected = h.controls.camera.position.toArray();
    assert.notDeepEqual(h.renderer.cameraPosition, expected);
    for (const type of ['pointerdown', 'pointerup']) {
        h.renderer.domElement.dispatchEvent(Object.assign(new Event(type), { clientX: 400, clientY: 250 }));
    }
    assert.deepEqual(h.pickedCamera, expected);
    assert.equal(h.renderer.renders, 1);
    dispose();
});
