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
    let renderer, controls, observer, frame;
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
        render(scene) { this.scene = scene; this.renders++; }
        dispose() { this.disposed = true; }
        forceContextLoss() { this.contextLost = true; }
    }
    class Controls extends EventTarget {
        target = new THREE.Vector3();
        constructor() { super(); controls = this; }
        listenToKeyEvents() {}
        update() { this.dispatchEvent(new Event('change')); }
        dispose() { this.disposed = true; }
    }
    const context = {
        ...data, AbortController, Event, URLSearchParams, Intl, Float32Array,
        THREE: { ...THREE, WebGLRenderer: Renderer, PerspectiveCamera: camera ? THREE.PerspectiveCamera : class { constructor() { throw new Error('Initialisation failed'); } } },
        OrbitControls: Controls,
        document: { documentElement: { lang: 'en-GB' }, createElement: () => new Element(), createDocumentFragment: () => new Element() },
        location: { search: '' }, window: { devicePixelRatio: 1 },
        requestAnimationFrame(callback) { frame = callback; return 1; },
        cancelAnimationFrame() {},
        ResizeObserver: class {
            constructor(callback) { observer = this; this.callback = callback; }
            observe() {}
            disconnect() { this.disconnected = true; }
        },
    };
    runInNewContext(source, context);
    return { root, nodes, mount: context.mountGalaxy, get renderer() { return renderer; }, get controls() { return controls; }, get observer() { return observer; }, get frame() { return frame; } };
}
const host = { id: 'far', name: 'Distant system', planet_count: 1, distance_pc: 100, x_pc: 100, y_pc: 0, z_pc: 0,
    galactocentric_x_pc: -8022, galactocentric_y_pc: 0, galactocentric_z_pc: 20.8 };

test('selection still expands the distance filter, and disposal releases GPU resources and listeners', () => {
    const h = harness();
    const dispose = h.mount(h.root, [host]);
    const picker = h.nodes.get('[data-system]');
    picker.value = 'far';
    picker.dispatchEvent(new Event('change'));
    assert.equal(h.nodes.get('[data-radius]').value, 'all');
    assert.equal(h.nodes.get('[data-selection]').children[0].textContent, 'Distant system');
    assert.equal(h.nodes.get('[data-selection]').children.at(-1).href, '/systems/far');
    assert.equal(h.renderer.domElement.focused, true);
    let geometries = 0, materials = 0;
    h.renderer.scene.traverse(object => {
        object.geometry?.addEventListener('dispose', () => { geometries++; });
        object.material?.addEventListener('dispose', () => { materials++; });
    });
    h.observer.callback();
    const renders = h.renderer.renders;
    dispose(); dispose();
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
    const dispose = h.mount(h.root, [host]);
    const picker = h.nodes.get('[data-system]');
    assert.equal(picker.disabled, false);
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
