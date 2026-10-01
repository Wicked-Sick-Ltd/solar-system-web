import { loadWorkspace } from './workspace-store.js';

export function mountNightSites(form, storage = null) {
    const doc = form.ownerDocument, view = doc.defaultView;
    const get = name => form.querySelector(`[data-night-${name}]`);
    const choice = get('site-choice');
    function report(error) { get('site-error').textContent = `${error.message} You can still enter the planning fields manually.`; }
    function copy() {
        get('site-error').textContent = '';
        try {
            const site = loadWorkspace(storage).sites.find(row => row.id === choice.value);
            if (!site) throw new Error('This site is no longer saved. Reload to refresh the list.');
            for (const [field, value] of Object.entries({ lat: site.latitude, lon: site.longitude, timezone: site.timezone,
                min_altitude_deg: site.minAltitudeDeg, horizon: site.horizonMask?.map(point => `${point.azimuthDeg} ${point.minAltitudeDeg}`).join('\n') ?? '' })) {
                form.elements[field].value = String(value);
            }
            get('site-status').textContent = `${site.name} copied into the form. Review the values before calculating; nothing was saved or activated.`;
        } catch (error) { report(error); }
    }
    try {
        storage ??= view.localStorage;
        const workspace = loadWorkspace(storage);
        choice.replaceChildren();
        for (const site of workspace.sites) { const option = doc.createElement('option'); option.value = site.id; option.textContent = site.name; choice.append(option); }
        get('sites').hidden = workspace.sites.length === 0;
    } catch (error) { report(error); }
    get('site-apply').addEventListener('click', copy);
    return { dispose() { get('site-apply').removeEventListener('click', copy); } };
}
