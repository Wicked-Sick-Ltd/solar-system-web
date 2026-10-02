import { mountJournal } from './journal-ui.js';
let mounted, currentRoot;
function dispose() { mounted?.dispose(); mounted = null; currentRoot = null; }
function initialise() {
    const root = document.querySelector('[data-observing-journal]');
    if (mounted && root === currentRoot) return;
    dispose(); if (root) { mounted = mountJournal(root); currentRoot = root; }
}
document.addEventListener('livewire:navigated', initialise);
document.addEventListener('livewire:navigating', dispose);
window.addEventListener('pagehide', dispose);
window.addEventListener('pageshow', event => { if (event.persisted) initialise(); });
if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', initialise, { once: true });
else initialise();
