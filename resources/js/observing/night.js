import { mountNightSites } from './night-sites.js';
import { mountNightSession, readNightSession } from './night-session.js';
import { mountEquipmentSuggestions } from './equipment-suggestions-ui.js';
let mounted, session, equipment, currentRoot;
function dispose() { mounted?.dispose(); session?.dispose(); equipment?.dispose(); mounted = null; session = null; equipment = null; currentRoot = null; }
function initialise() {
    const root = document.querySelector('[data-night-form]');
    if (mounted && root === currentRoot) return;
    dispose(); if (root) { mounted = mountNightSites(root); currentRoot = root;
        const sessionRoot = document.querySelector('[data-night-session]');
        if (sessionRoot) {
            session = mountNightSession(sessionRoot);
            let targets = [];
            try { targets = readNightSession(sessionRoot).targets; } catch { /* Download error is already visible; temporary optics still work. */ }
            equipment = mountEquipmentSuggestions(document.querySelector('[data-equipment-suggestions]'), { targets });
        } }
}
document.addEventListener('livewire:navigated', initialise);
document.addEventListener('livewire:navigating', dispose);
window.addEventListener('pagehide', dispose);
window.addEventListener('pageshow', event => { if (event.persisted) initialise(); });
if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', initialise, { once: true });
else initialise();
