const MAX_BYTES = 160000;
const bytes = value => new TextEncoder().encode(value).length;

// Input is server-generated, validated scientific metadata. This is a download
// formatter, not an importer or a second astronomical validation implementation.
export function sessionExport(session, includeLocation = false) {
    const out = structuredClone(session);
    if (out.export_schema_version !== 1 || out.kind !== 'public-universe-observing-session') throw new Error('This session format is not supported.');
    out.location_included = includeLocation === true;
    if (!out.location_included) {
        delete out.input.lat; delete out.input.lon; delete out.input.horizon_mask;
        delete out.constraints.horizon_mask;
    }
    out.reproducibility.inputs_complete = out.location_included;
    out.reproducibility.location_note = out.location_included
        ? 'Rounded coordinates and supplied terrain are included. Keep this file private.'
        : 'Coordinates and terrain are omitted. Dates, timezone and derived windows remain; they can still reveal observing context. Supply the original location and terrain to repeat the calculation.';
    const text = JSON.stringify(out, null, 2);
    if (bytes(text) > MAX_BYTES) throw new Error('The session summary exceeds the download limit.');
    return text;
}

function cell(value) {
    let text = value === null || value === undefined ? '' : String(value);
    if (/^[\s\u0000-\u001f]*[=+\-@]/u.test(text)) text = `'${text}`;
    return `"${text.replaceAll('"', '""')}"`;
}
export function sessionCsv(session, includeLocation = false) {
    const data = JSON.parse(sessionExport(session, includeLocation));
    const rows = [['target_id', 'name', 'status', 'start_utc', 'end_utc', 'timezone', 'provider', 'source_snapshot_sha256', 'location_included', 'latitude_deg', 'longitude_deg', 'scope']];
    for (const target of data.targets) {
        for (const window of target.windows.length ? target.windows : [null]) {
            rows.push([target.id, target.name, target.status, window?.start_utc, window?.end_utc, data.input.timezone, data.method.provider, target.catalogue?.snapshot_sha256, data.location_included, data.input.lat, data.input.lon, 'Window summary only; use companion JSON for complete constraints and provenance.']);
        }
    }
    return rows.map(row => row.map(cell).join(',')).join('\r\n') + '\r\n';
}

export function readNightSession(root) {
    const text = root.querySelector('[data-night-session-data]').textContent;
    if (bytes(text) > MAX_BYTES) throw new Error('The session summary is too large.');
    const session = JSON.parse(text);
    sessionExport(session);
    return session;
}

export function mountNightSession(root) {
    const doc = root.ownerDocument, view = doc.defaultView;
    const get = name => root.querySelector(`[data-night-session-${name}]`);
    const listeners = [];
    const on = (element, type, action) => { element.addEventListener(type, action); listeners.push(() => element.removeEventListener(type, action)); };
    let session;
    try {
        session = readNightSession(root);
    } catch {
        get('error').textContent = 'Session downloads are unavailable. The visible calculation can still be read.';
        return { dispose() {} };
    }
    const privateNodes = root.querySelectorAll('[data-night-private-location]');
    const privacy = () => { for (const node of privateNodes) node.classList.toggle('print:hidden', !get('locations').checked); };
    get('locations').checked = false; privacy();
    on(get('locations'), 'change', privacy);
    for (const format of ['json', 'csv']) on(get(format), 'click', () => {
        try {
            get('error').textContent = '';
            const body = format === 'json' ? sessionExport(session, get('locations').checked) : sessionCsv(session, get('locations').checked);
            const url = view.URL.createObjectURL(new Blob([body], { type: format === 'json' ? 'application/json' : 'text/csv;charset=utf-8' }));
            const anchor = doc.createElement('a'); anchor.href = url; anchor.download = `public-universe-night-${session.input.date}.${format}`;
            anchor.click(); view.setTimeout(() => view.URL.revokeObjectURL(url), 1000);
            get('status').textContent = `Last download: ${format.toUpperCase()} prepared with ${get('locations').checked ? 'rounded coordinates and terrain' : 'coordinates and terrain omitted'}. No new calculation or upload was made.`;
        } catch (error) { get('error').textContent = error.message; }
    });
    on(get('print'), 'click', () => { privacy(); view.print(); });
    get('controls').hidden = false;
    return { dispose() { listeners.forEach(remove => remove()); get('locations').checked = false; privacy(); } };
}
