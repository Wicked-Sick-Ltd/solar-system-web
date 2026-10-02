import { validateHorizonMask } from './horizon.js';

// The historical key is retained so existing version-one workspaces are found.
export const WORKSPACE_KEY = 'public_universe_observing_v1';
export const LOCATION_KEY = 'observer_location';
export const LEGACY_MAX_BYTES = 131072;
export const MAX_BYTES = 262144;
export const MAX_ENTRIES = 100;
export const EQUIPMENT_KINDS = ['telescope', 'binocular', 'eyepiece', 'barlow', 'reducer', 'camera'];
const UUID = /^[0-9a-f]{8}-[0-9a-f]{4}-[1-8][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i;

export function emptyWorkspace() {
    return { schemaVersion: 2, equipment: [], sites: [], activeSiteId: null };
}
function fail(message) { throw new Error(message); }
function shape(value, keys, label) {
    if (!value || typeof value !== 'object' || Array.isArray(value)
        || Object.keys(value).some(key => !keys.includes(key))
        || keys.some(key => !Object.hasOwn(value, key))) fail(`${label} has missing or unsupported fields.`);
}
function name(value) {
    if (typeof value !== 'string' || !value.trim() || value.trim().length > 100 || /[\u0000-\u001f\u007f]/.test(value)) fail('Names must contain 1 to 100 characters, without control characters.');
    return value.trim();
}
function number(value, min, max, label, nullable = false) {
    if (nullable && value === null) return null;
    if (typeof value !== 'number' || !Number.isFinite(value) || value < min || value > max) fail(`${label} must be a number from ${min} to ${max}.`);
    return value;
}
function id(value) {
    if (typeof value !== 'string' || !UUID.test(value)) fail('Every entry needs a valid UUID.');
    return value.toLowerCase();
}
function equipment(value, version) {
    const fields = {
        telescope: ['apertureMm', 'focalLengthMm'], binocular: ['apertureMm', 'magnification'],
        eyepiece: ['focalLengthMm', 'apparentFovDeg', 'fieldStopMm'], barlow: ['factor'], reducer: ['factor'],
        camera: ['sensorWidthMm', 'sensorHeightMm', 'pixelSizeUm'],
    };
    if (!value || !Object.hasOwn(fields, value.kind) || (version === 1 && value.kind === 'camera')) fail('Unsupported equipment type.');
    shape(value, ['id', 'name', 'kind', ...fields[value.kind]], 'Equipment');
    const out = { id: id(value.id), name: name(value.name), kind: value.kind };
    if ('apertureMm' in value) out.apertureMm = number(value.apertureMm, 1, 10000, 'Aperture (mm)');
    if ('focalLengthMm' in value) out.focalLengthMm = number(value.focalLengthMm, 0.1, 100000, 'Focal length (mm)');
    if ('magnification' in value) out.magnification = number(value.magnification, 0.1, 1000, 'Magnification');
    if ('apparentFovDeg' in value) out.apparentFovDeg = number(value.apparentFovDeg, 0.1, 180, 'Apparent field (degrees)', true);
    if ('fieldStopMm' in value) out.fieldStopMm = number(value.fieldStopMm, 0.1, 500, 'Field stop (mm)', true);
    if ('factor' in value) out.factor = number(value.factor, value.kind === 'barlow' ? 1 : 0.01, value.kind === 'barlow' ? 20 : 1, 'Optical factor');
    if (value.kind === 'camera') {
        out.sensorWidthMm = number(value.sensorWidthMm, 0.01, 1000, 'Sensor width (mm)');
        out.sensorHeightMm = number(value.sensorHeightMm, 0.01, 1000, 'Sensor height (mm)');
        out.pixelSizeUm = number(value.pixelSizeUm, 0.01, 1000, 'Pixel size (micrometres)', true);
        if (out.pixelSizeUm !== null && out.pixelSizeUm / 1000 > Math.min(out.sensorWidthMm, out.sensorHeightMm)) fail('Pixel size cannot exceed a sensor dimension.');
    }
    return out;
}
function site(value, version) {
    shape(value, ['id', 'name', 'latitude', 'longitude', 'timezone', 'minAltitudeDeg', ...(version === 2 ? ['horizonMask'] : [])], 'Site');
    if (typeof value.timezone !== 'string' || value.timezone.length > 100 || !/^[A-Za-z_]+(?:\/[A-Za-z0-9_+\-]+)*$/.test(value.timezone)) fail('Choose a recognised IANA timezone, such as Europe/London or UTC.');
    let timezone;
    try { timezone = new Intl.DateTimeFormat('en', { timeZone: value.timezone }).resolvedOptions().timeZone; } catch { fail('Choose a recognised IANA timezone, such as Europe/London or UTC.'); }
    return {
        id: id(value.id), name: name(value.name),
        latitude: Math.round(number(value.latitude, -90, 90, 'Latitude') * 100) / 100,
        longitude: Math.round(number(value.longitude, -180, 180, 'Longitude') * 100) / 100,
        timezone, minAltitudeDeg: number(value.minAltitudeDeg, 0, 90, 'Minimum altitude (degrees)'),
        horizonMask: version === 1 ? null : validateHorizonMask(value.horizonMask),
    };
}
export function validateWorkspace(value) {
    shape(value, ['schemaVersion', 'equipment', 'sites', 'activeSiteId'], 'Workspace');
    if (![1, 2].includes(value.schemaVersion)) fail('This workspace version is not supported. Nothing was changed.');
    for (const field of ['equipment', 'sites']) {
        if (!Array.isArray(value[field]) || value[field].length > MAX_ENTRIES) fail(`A workspace can contain at most ${MAX_ENTRIES} ${field}.`);
    }
    const out = { schemaVersion: 2, equipment: value.equipment.map(entry => equipment(entry, value.schemaVersion)), sites: value.sites.map(entry => site(entry, value.schemaVersion)), activeSiteId: value.activeSiteId === null ? null : id(value.activeSiteId) };
    const ids = [...out.equipment, ...out.sites].map(entry => entry.id);
    if (new Set(ids).size !== ids.length) fail('Entry IDs must be unique.');
    if (out.activeSiteId !== null && !out.sites.some(entry => entry.id === out.activeSiteId)) fail('The selected site does not exist in this workspace.');
    if (value.schemaVersion === 1) {
        const legacy = { ...out, schemaVersion: 1, sites: out.sites.map(({ horizonMask, ...entry }) => entry) };
        if (new TextEncoder().encode(JSON.stringify(legacy)).length > LEGACY_MAX_BYTES) fail('This version-one workspace exceeds the original 128 KiB limit.');
    }
    if (new TextEncoder().encode(JSON.stringify(out)).length > MAX_BYTES) fail('This workspace exceeds the 256 KiB limit.');
    return out;
}
export function parseWorkspace(text) {
    if (typeof text !== 'string' || new TextEncoder().encode(text).length > MAX_BYTES) fail('Choose a workspace JSON file no larger than 256 KiB.');
    let data;
    try { data = JSON.parse(text); } catch { fail('The file is not readable JSON. Nothing was changed.'); }
    if (data?.schemaVersion === 1 && new TextEncoder().encode(text).length > LEGACY_MAX_BYTES) fail('Version-one files must fit the original 128 KiB limit.');
    return validateWorkspace(data);
}
export function loadWorkspace(storage) {
    const saved = storage.getItem(WORKSPACE_KEY);
    return saved === null ? emptyWorkspace() : parseWorkspace(saved);
}
export function saveWorkspace(storage, workspace) {
    const clean = validateWorkspace(workspace);
    storage.setItem(WORKSPACE_KEY, JSON.stringify(clean));
    return clean;
}
export function putEntry(workspace, collection, entry) {
    if (!['equipment', 'sites'].includes(collection)) fail('Unsupported entry collection.');
    const existing = workspace[collection].some(item => item.id === entry.id);
    return validateWorkspace({ ...workspace, [collection]: existing ? workspace[collection].map(item => item.id === entry.id ? entry : item) : [...workspace[collection], entry] });
}
export function removeEntry(workspace, collection, entryId) {
    if (!['equipment', 'sites'].includes(collection)) fail('Unsupported entry collection.');
    return validateWorkspace({ ...workspace, [collection]: workspace[collection].filter(entry => entry.id !== entryId), activeSiteId: collection === 'sites' && workspace.activeSiteId === entryId ? null : workspace.activeSiteId });
}
export function activeLocationMatches(storage, workspace) {
    const selected = workspace.sites.find(entry => entry.id === workspace.activeSiteId);
    if (!selected) return false;
    try {
        const value = JSON.parse(storage.getItem(LOCATION_KEY));
        return value?.lat === selected.latitude && value?.lon === selected.longitude;
    } catch { return false; }
}
// localStorage has no multi-key transaction. Restore both keys if a browser
// refuses a write; surface failures and reload rather than claiming success.
export function activateSite(storage, workspace, entryId) {
    const selected = workspace.sites.find(entry => entry.id === entryId);
    if (!selected) fail('This site no longer exists.');
    const before = storage.getItem(WORKSPACE_KEY);
    const oldLocation = storage.getItem(LOCATION_KEY);
    const next = validateWorkspace({ ...workspace, activeSiteId: entryId });
    try {
        storage.setItem(LOCATION_KEY, JSON.stringify({ lat: selected.latitude, lon: selected.longitude }));
        saveWorkspace(storage, next);
    } catch (error) {
        try {
            oldLocation === null ? storage.removeItem(LOCATION_KEY) : storage.setItem(LOCATION_KEY, oldLocation);
            before === null ? storage.removeItem(WORKSPACE_KEY) : storage.setItem(WORKSPACE_KEY, before);
        } catch { fail('The browser refused storage and could not restore the previous selection. Check Your settings before using a sky calculation.'); }
        throw error;
    }
    return next;
}
