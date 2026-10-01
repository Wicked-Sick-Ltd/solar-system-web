// Keep the initial galaxy entry small: neither catalogue JSON nor Three.js is
// requested until the visitor asks for 3D. The native directory stays available.
export function mountGalaxyPage(root, { loadRenderer, fetchMap = globalThis.fetch }) {
    const document = root.ownerDocument;
    const button = root.querySelector('[data-load-map]');
    const status = root.querySelector('[data-map-status]');
    const viewport = root.querySelector('[data-viewport]');
    const controls = ['[data-view]', '[data-radius]', '[data-reset]', '[data-system]'].map(selector => root.querySelector(selector));
    const events = new AbortController();
    let request, pending, disposeRenderer, focusEvents, disposed = false;

    function idle() {
        button.hidden = false;
        button.disabled = false;
        button.textContent = 'Load 3D map';
        viewport.setAttribute('aria-busy', 'false');
        status.textContent = 'Load the map to explore in 3D, or browse the accessible system directory.';
        controls.forEach(control => { control.disabled = true; });
        root.querySelector('[data-sun-label]').hidden = true;
        root.querySelector('[data-centre-label]').hidden = true;
    }
    idle();

    function load() {
        if (disposed || pending || disposeRenderer) return pending;
        request = new AbortController();
        // Disabling the activating button can drop browser focus onto body.
        // Continue into the map only while that visitor is still waiting here.
        let focusOnReady = document.activeElement === button || document.activeElement === document.body;
        focusEvents = new AbortController();
        const stopFollowing = () => { focusOnReady = false; };
        document.addEventListener('focusin', event => {
            if (![button, document.body, document.documentElement].includes(event.target)) stopFollowing();
        }, { signal: focusEvents.signal });
        document.addEventListener('pointerdown', event => {
            if (!button.contains(event.target)) stopFollowing();
        }, { signal: focusEvents.signal, capture: true });
        document.addEventListener('keydown', stopFollowing, { signal: focusEvents.signal, capture: true });
        const shouldFocus = () => focusOnReady && [button, document.body, document.documentElement].includes(document.activeElement);
        button.disabled = true;
        button.textContent = 'Loading 3D map…';
        status.textContent = 'Loading the map and measured systems…';
        viewport.setAttribute('aria-busy', 'true');
        pending = (async () => {
            try {
                const [module, hosts] = await Promise.all([
                    loadRenderer(),
                    (async () => {
                        const response = await fetchMap(root.dataset.dataUrl, {
                            signal: request.signal, headers: { Accept: 'application/json' },
                        });
                        if (!response.ok) throw new Error('Map unavailable');
                        const data = await response.json();
                        if (!Array.isArray(data.hosts)) throw new Error('Invalid map response');
                        return data.hosts;
                    })(),
                ]);
                if (disposed) return;
                disposeRenderer = module.mountGalaxy(root, hosts, { focusOnReady: shouldFocus() });
                button.hidden = true;
                viewport.setAttribute('aria-busy', 'false');
            } catch {
                request.abort();
                if (disposed) return;
                idle();
                button.textContent = 'Retry loading 3D map';
                status.textContent = 'The map could not load. Try again, or use the accessible system directory.';
                if (shouldFocus()) button.focus({ preventScroll: true });
            } finally {
                focusEvents.abort();
                pending = null;
            }
        })();
        return pending;
    }
    button.addEventListener('click', load, { signal: events.signal });

    return {
        load,
        dispose() {
            if (disposed) return;
            disposed = true;
            events.abort();
            request?.abort();
            focusEvents?.abort();
            disposeRenderer?.();
        },
    };
}
