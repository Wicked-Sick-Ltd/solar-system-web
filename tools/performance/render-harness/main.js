import { mountGalaxy } from '../../../resources/js/galaxy-renderer.js';
import { metrics } from './metrics.js';
const hosts = Array.from({ length: 5000 }, (_, i) => {
    const distance = 1 + i / 10, azimuth = i * 2.399963229728653, z = ((i % 101) / 50 - 1) * distance * 0.5;
    const r = Math.sqrt(distance ** 2 - z ** 2), x = r * Math.cos(azimuth), y = r * Math.sin(azimuth);
    return { id: `synthetic-${i}`, name: `Synthetic host ${i}`, planet_count: 1, distance_pc: distance,
        distance_error_plus_pc: null, distance_error_minus_pc: null, x_pc: x, y_pc: y, z_pc: z,
        galactocentric_x_pc: x - 8122, galactocentric_y_pc: y, galactocentric_z_pc: z + 20.8 };
});
const fixtureHash = [...new Uint8Array(await crypto.subtle.digest('SHA-256', new TextEncoder().encode(JSON.stringify(hosts))))].map(b => b.toString(16).padStart(2, '0')).join('');
let dispose = null, updateTimer = null;
const report = () => ({ schema: 1, expectedRendererRevision: '44b66f4', hashes: __HARNESS_IDENTITY__, fixtureSha256: fixtureHash,
    fixtureRows: hosts.length, browser: navigator.userAgent, devicePixelRatio, mapCssPixels: { width: document.querySelector('[data-viewport]').clientWidth, height: document.querySelector('[data-viewport]').clientHeight },
    definitions: { cpuCallMs: 'Synchronous renderer.render call only; excludes timer-query setup and report update; not frame presentation.', gpuMs: 'TIME_ELAPSED_EXT nanoseconds / 1e6 only when non-disjoint available query; unknown is null.', memory: 'Three renderer.info counts, not bytes, heap/GPU allocation or physical reclaim proof.' }, ...metrics.snapshot() });
function show() { updateTimer = null; document.querySelector('#measurements').textContent = JSON.stringify(report(), null, 2); }
window.addEventListener('harness-metrics', () => { if (updateTimer === null) updateTimer = setTimeout(show, 100); });
document.querySelector('#mount').addEventListener('click', () => {
    if (dispose) return;
    document.querySelector('#mount').disabled = true;
    try { dispose = mountGalaxy(document.querySelector('#map'), hosts); }
    catch (error) { document.querySelector('[data-map-status]').textContent = `Mount failed: ${error.message}`; }
    show();
});
document.querySelector('#phase').addEventListener('click', () => { metrics.phase('interaction'); show(); });
document.querySelector('#dispose').addEventListener('click', () => { dispose?.(); dispose = null; show(); });
document.querySelector('#export').addEventListener('click', () => {
    show(); const url = URL.createObjectURL(new Blob([JSON.stringify(report(), null, 2)], { type: 'application/json' }));
    const link = document.createElement('a'); link.href = url; link.download = 'map-render-metrics.json'; link.click();
    setTimeout(() => URL.revokeObjectURL(url), 1000);
});
show();
