import { mountObservingSync } from './sync-ui.js';
let mounted;
function dispose() { mounted?.dispose(); mounted = null; }
function initialise() {
    dispose(); const root = document.querySelector('[data-observing-sync]');
    if (root) mounted = mountObservingSync(root);
}
document.addEventListener('livewire:navigated', initialise);
document.addEventListener('livewire:navigating', dispose);
window.addEventListener('pagehide', dispose);
window.addEventListener('pageshow', event => { if (event.persisted) window.location.reload(); });
if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', initialise, { once: true });
else initialise();
