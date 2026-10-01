import { mountNightSites } from './night-sites.js';
import { mountNightSession } from './night-session.js';
let mounted, session, currentRoot;
function dispose() { mounted?.dispose(); session?.dispose(); mounted = null; session = null; currentRoot = null; }
function initialise() {
    const root = document.querySelector('[data-night-form]');
    if (mounted && root === currentRoot) return;
    dispose(); if (root) { mounted = mountNightSites(root); currentRoot = root;
        const sessionRoot = document.querySelector('[data-night-session]');
        if (sessionRoot) session = mountNightSession(sessionRoot); }
}
document.addEventListener('livewire:navigated', initialise);
document.addEventListener('livewire:navigating', dispose);
window.addEventListener('pagehide', dispose);
window.addEventListener('pageshow', event => { if (event.persisted) initialise(); });
if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', initialise, { once: true });
else initialise();
