import { mountWorkspace } from './workspace-ui.js';

let current;
let currentRoot;
function dispose() { current?.dispose(); current = null; currentRoot = null; }
function initialise() {
    const root = document.querySelector('[data-observing-workspace]');
    if (current && currentRoot === root) return;
    dispose();
    if (root) { current = mountWorkspace(root); currentRoot = root; }
}
document.addEventListener('livewire:navigated', initialise);
document.addEventListener('livewire:navigating', dispose);
window.addEventListener('pagehide', dispose);
window.addEventListener('pageshow', event => { if (event.persisted) initialise(); });
// Vite may finish after Livewire's initial navigated event. An idempotent mount
// also handles the first full-page visit without relying on Alpine timing.
if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', initialise, { once: true });
else initialise();
