import { mountGalaxyPage } from './galaxy-loader.js';

let current;
function dispose() {
    current?.dispose();
    current = null;
}

// Livewire emits navigated on initial load too; do not start a second DOM-ready load.
function initialise() {
    dispose();
    const root = document.querySelector('[data-galaxy-root]');
    if (root) current = mountGalaxyPage(root, {
        loadRenderer: () => import('./galaxy-renderer.js'),
    });
}
document.addEventListener('livewire:navigated', initialise);
document.addEventListener('livewire:navigating', dispose);
window.addEventListener('pagehide', dispose);
// A browser back/forward-cache restore does not emit Livewire navigation events.
window.addEventListener('pageshow', event => {
    if (event.persisted) initialise();
});
