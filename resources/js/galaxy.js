import * as THREE from 'three';
import { OrbitControls } from 'three/addons/controls/OrbitControls.js';
import { PARSEC_TO_LY, SUN_GALACTOCENTRIC, position, visibleHosts } from './galaxy-data.js';

let disposeCurrent;
let generation = 0;

async function initialise() {
    const currentGeneration = ++generation;
    disposeCurrent?.();
    const root = document.querySelector('[data-galaxy-root]');
    if (!root) return;
    const viewport = root.querySelector('[data-viewport]');
    const status = root.querySelector('[data-map-status]');
    const picker = root.querySelector('[data-system]');
    const selection = root.querySelector('[data-selection]');
    const modeControl = root.querySelector('[data-view]');
    const radiusControl = root.querySelector('[data-radius]');
    const reset = root.querySelector('[data-reset]');
    const sunLabel = root.querySelector('[data-sun-label]');
    const centreLabel = root.querySelector('[data-centre-label]');
    const events = new AbortController();
    let renderer, controls, frame, observer;
    let mode = 'nearby', shown = [], pointCloud, selectedMarker, radius = 25;
    let scene, camera, sun, galaxyOutline;
    let hosts;
    try {
        const response = await fetch(root.dataset.dataUrl, { headers: { Accept: 'application/json' } });
        if (!response.ok) throw new Error('Map unavailable');
        hosts = (await response.json()).hosts;
        if (!Array.isArray(hosts)) throw new Error('Invalid map response');
        if (currentGeneration !== generation) return;
    } catch {
        status.textContent = 'The map could not load. Use the linked system list below, or reload to try again.';
        modeControl.disabled = radiusControl.disabled = reset.disabled = picker.disabled = true;
        sunLabel.hidden = true;
        return;
    }
    const byId = new Map(hosts.map(host => [host.id, host]));
    const distanceText = value => new Intl.NumberFormat(document.documentElement.lang, { maximumFractionDigits: 2 }).format(value * PARSEC_TO_LY);

    function selectHost(id, moveCamera = true) {
        const host = byId.get(id);
        if (!host) return;
        picker.value = id;
        const title = document.createElement('h2');
        title.className = 'text-xl';
        title.textContent = host.name;
        const detail = document.createElement('p');
        detail.className = 'mt-3';
        detail.textContent = `${host.planet_count} catalogued planets · ${distanceText(host.distance_pc)} light-years`;
        const uncertainty = document.createElement('p');
        uncertainty.className = 'mt-2 text-sm';
        if (Number.isFinite(host.distance_error_plus_pc) && Number.isFinite(host.distance_error_minus_pc)) {
            uncertainty.textContent = `Distance uncertainty: +${distanceText(Math.abs(host.distance_error_plus_pc))} / −${distanceText(Math.abs(host.distance_error_minus_pc))} light-years`;
        }
        const link = document.createElement('a');
        link.href = `/systems/${encodeURIComponent(host.id)}`;
        link.className = 'mt-4 inline-block underline';
        link.textContent = 'Explore system →';
        selection.replaceChildren(title, detail, uncertainty, link);
        if (!renderer) return;
        if (!shown.some(item => item.id === id)) {
            radiusControl.value = 'all';
            rebuild();
        }
        selectedMarker.position.fromArray(position(host, mode));
        selectedMarker.visible = true;
        if (moveCamera) {
            const target = selectedMarker.position;
            const distance = mode === 'galaxy' ? 1500 : Math.max(3, Math.min(host.distance_pc * 0.4, 150));
            controls.target.copy(target);
            camera.position.copy(target).add(new THREE.Vector3(distance, distance * 0.6, distance));
            controls.update();
        }
        draw();
    }

    picker.addEventListener('change', () => selectHost(picker.value), { signal: events.signal });
    disposeCurrent = () => {
        events.abort();
        cancelAnimationFrame(frame);
        observer?.disconnect();
        controls?.dispose();
        scene?.traverse(object => {
            object.geometry?.dispose();
            object.material?.dispose();
        });
        renderer?.dispose();
        renderer?.domElement.remove();
    };
    try {
        renderer = new THREE.WebGLRenderer({ antialias: true, alpha: false });
    } catch {
        status.textContent = '3D is unavailable in this browser. Choose a system above or use the linked list below.';
        sunLabel.hidden = true;
        modeControl.disabled = radiusControl.disabled = reset.disabled = true;
        const selected = new URLSearchParams(location.search).get('host');
        if (selected) selectHost(selected);
        return;
    }
    renderer.setPixelRatio(Math.min(window.devicePixelRatio, 2));
    renderer.setClearColor('#070c18');
    renderer.domElement.tabIndex = 0;
    renderer.domElement.setAttribute('aria-label', '3D exoplanet host map. Arrow keys pan, plus and minus zoom. Use the system picker for keyboard selection.');
    renderer.domElement.style.display = 'block';
    viewport.prepend(renderer.domElement);
    scene = new THREE.Scene();
    camera = new THREE.PerspectiveCamera(50, 1, 0.001, 1000000);
    controls = new OrbitControls(camera, renderer.domElement);
    controls.enableDamping = false;
    controls.minDistance = 0.1;
    controls.maxDistance = 100000;
    controls.listenToKeyEvents(renderer.domElement);
    controls.addEventListener('change', draw);
    sun = new THREE.Points(new THREE.BufferGeometry().setFromPoints([new THREE.Vector3()]), new THREE.PointsMaterial({ color: '#ffd58b', size: 11, sizeAttenuation: false, depthTest: false }));
    sun.renderOrder = 2;
    scene.add(sun);
    selectedMarker = new THREE.Points(new THREE.BufferGeometry().setFromPoints([new THREE.Vector3()]), new THREE.PointsMaterial({ color: '#ffffff', size: 12, sizeAttenuation: false, depthTest: false }));
    selectedMarker.visible = false;
    scene.add(selectedMarker);
    // Context only: a schematic 15 kpc disk outline, not a measured spiral-arm map.
    const circle = Array.from({ length: 129 }, (_, i) => new THREE.Vector3(Math.cos(i * Math.PI / 64) * 15000, 0, Math.sin(i * Math.PI / 64) * 15000));
    galaxyOutline = new THREE.LineLoop(new THREE.BufferGeometry().setFromPoints(circle), new THREE.LineBasicMaterial({ color: '#44506b', transparent: true, opacity: 0.7 }));
    scene.add(galaxyOutline);

    function label(element, point) {
        const projected = point.clone().project(camera);
        element.hidden = projected.z > 1 || projected.z < -1 || Math.abs(projected.x) > 1 || Math.abs(projected.y) > 1;
        element.style.left = `${(projected.x + 1) * viewport.clientWidth / 2 + 9}px`;
        element.style.top = `${(-projected.y + 1) * viewport.clientHeight / 2 - 8}px`;
    }
    function draw() {
        if (!renderer || !camera) return;
        renderer.render(scene, camera);
        label(sunLabel, sun.position);
        if (mode === 'galaxy') label(centreLabel, new THREE.Vector3());
        else centreLabel.hidden = true;
    }
    function resetCamera() {
        controls.target.set(mode === 'galaxy' ? -4000 : 0, 0, 0);
        const size = mode === 'galaxy' ? 24000 : radius * 2;
        camera.position.copy(controls.target).add(new THREE.Vector3(size * 0.5, size * 0.65, size));
        controls.update();
        draw();
    }
    function rebuild() {
        mode = modeControl.value;
        shown = visibleHosts(hosts, radiusControl.value);
        radius = radiusControl.value === 'all' ? Math.max(25, ...shown.map(h => h.distance_pc)) : Number(radiusControl.value);
        if (pointCloud) { scene.remove(pointCloud); pointCloud.geometry.dispose(); pointCloud.material.dispose(); }
        const positions = new Float32Array(shown.flatMap(host => position(host, mode)));
        const geometry = new THREE.BufferGeometry();
        geometry.setAttribute('position', new THREE.BufferAttribute(positions, 3));
        pointCloud = new THREE.Points(geometry, new THREE.PointsMaterial({ color: '#84bbf4', size: 5, sizeAttenuation: false }));
        scene.add(pointCloud);
        sun.position.fromArray(mode === 'galaxy' ? SUN_GALACTOCENTRIC : [0, 0, 0]);
        galaxyOutline.visible = mode === 'galaxy';
        selectedMarker.visible = false;
        status.textContent = `${shown.length} measured systems · ${mode === 'galaxy' ? 'Schematic galaxy outline · 30 kpc diameter' : `Sun-centred · radius ${distanceText(radius)} light-years`}`;
        resetCamera();
        if (picker.value && shown.some(host => host.id === picker.value)) selectHost(picker.value, false);
    }
    modeControl.addEventListener('change', () => { radiusControl.value = modeControl.value === 'galaxy' ? 'all' : '25'; rebuild(); }, { signal: events.signal });
    radiusControl.addEventListener('change', rebuild, { signal: events.signal });
    reset.addEventListener('click', resetCamera, { signal: events.signal });
    renderer.domElement.addEventListener('keydown', event => {
        if (!['+', '=', '-'].includes(event.key)) return;
        event.preventDefault();
        camera.position.sub(controls.target).multiplyScalar(event.key === '-' ? 1.2 : 1 / 1.2).add(controls.target);
        controls.update(); draw();
    }, { signal: events.signal });
    let pointerStart;
    renderer.domElement.addEventListener('pointerdown', event => { pointerStart = [event.clientX, event.clientY]; }, { signal: events.signal });
    renderer.domElement.addEventListener('pointerup', event => {
        if (!pointerStart || Math.hypot(event.clientX - pointerStart[0], event.clientY - pointerStart[1]) > 5) return;
        const rect = renderer.domElement.getBoundingClientRect();
        const mouse = new THREE.Vector2((event.clientX - rect.left) / rect.width * 2 - 1, -(event.clientY - rect.top) / rect.height * 2 + 1);
        const ray = new THREE.Raycaster();
        ray.params.Points.threshold = camera.position.distanceTo(controls.target) * 0.009;
        ray.setFromCamera(mouse, camera);
        const hit = ray.intersectObject(pointCloud)[0];
        if (hit && shown[hit.index]) selectHost(shown[hit.index].id, false);
    }, { signal: events.signal });
    observer = new ResizeObserver(() => {
        camera.aspect = viewport.clientWidth / viewport.clientHeight;
        camera.updateProjectionMatrix();
        renderer.setSize(viewport.clientWidth, viewport.clientHeight);
        cancelAnimationFrame(frame); frame = requestAnimationFrame(draw);
    });
    observer.observe(viewport);
    rebuild();
    const selected = new URLSearchParams(location.search).get('host');
    if (selected) selectHost(selected);
}

if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', initialise, { once: true });
else initialise();
document.addEventListener('livewire:navigated', initialise);
document.addEventListener('livewire:navigating', () => { generation++; disposeCurrent?.(); });
