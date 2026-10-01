import { telescopeOptics, binocularOptics, angularComparison } from './optics.js';

const finite = value => typeof value === 'number' && Number.isFinite(value);
const range = (value, min, max) => finite(value) && value >= min && value <= max;
const orderings = ['widest-field', 'lowest-power', 'highest-power'];
const compareId = (a, b) => a.id < b.id ? -1 : a.id > b.id ? 1 : 0;
function label(value) { return typeof value === 'string' && value.trim() !== '' && value.length <= 200; }

// Reuse the calculator's models rather than introducing an observing-success
// score. A major axis is a catalogue extent, not a visible outline or star size.
export function compareEyepieces({ instrument, eyepieces = [], accessory = null, diameterArcmin = null, binocularFieldDeg = null, order = 'lowest-power' }) {
    if (!orderings.includes(order)) throw new Error('Choose a supported comparison order.');
    if (!Array.isArray(eyepieces) || eyepieces.length > 101) throw new Error('Compare at most 100 saved eyepieces and one temporary eyepiece.');
    if (diameterArcmin !== null && (!range(diameterArcmin, Number.MIN_VALUE, 10800))) throw new Error('Angular size must be positive and no greater than 10,800 arcminutes.');
    if (binocularFieldDeg !== null && !range(binocularFieldDeg, Number.MIN_VALUE, 180)) throw new Error('Stated binocular field must be positive and no greater than 180 degrees.');
    if (!instrument || !['telescope', 'binocular'].includes(instrument.kind)) return [];
    const choices = instrument.kind === 'binocular' ? [instrument] : eyepieces;
    const ids = new Set();
    const rows = choices.map(choice => {
        if (!label(choice?.id) || !label(choice?.name) || ids.has(choice.id)
            || (instrument.kind === 'telescope' && choice.kind !== 'eyepiece')) throw new Error('Equipment choices must have distinct identities and names.');
        ids.add(choice.id);
        const optics = instrument.kind === 'binocular' ? binocularOptics(instrument, binocularFieldDeg) : telescopeOptics(instrument, choice, accessory);
        return { id: choice.id, name: choice.name, ...optics, comparison: angularComparison(optics.trueFovDeg, diameterArcmin) };
    });
    const key = order === 'widest-field' ? 'trueFovDeg' : 'magnification';
    const direction = order === 'lowest-power' ? 1 : -1;
    rows.sort((a, b) => {
        if (a[key] === null || b[key] === null) return a[key] === b[key] ? compareId(a, b) : a[key] === null ? 1 : -1;
        return direction * (a[key] - b[key]) || compareId(a, b);
    });
    return rows;
}

export function temporaryTelescope(apertureMm, focalLengthMm) {
    if (!range(apertureMm, 1, 10000) || !range(focalLengthMm, 0.1, 100000)) throw new Error('Temporary telescope: aperture must be 1–10,000 mm and focal length 0.1–100,000 mm.');
    return { id: 'temporary-telescope', name: 'Temporary telescope', kind: 'telescope', apertureMm, focalLengthMm };
}
export function temporaryEyepiece(focalLengthMm, apparentFovDeg, fieldStopMm) {
    if (!range(focalLengthMm, 0.1, 100000) || (apparentFovDeg !== null && !range(apparentFovDeg, 0.1, 180))
        || (fieldStopMm !== null && !range(fieldStopMm, 0.1, 500))) throw new Error('Temporary eyepiece: enter a focal length 0.1–100,000 mm; leave unknown apparent field or effective field stop blank.');
    return { id: 'temporary-eyepiece', name: 'Temporary eyepiece', kind: 'eyepiece', focalLengthMm, apparentFovDeg, fieldStopMm };
}

export function validateSuggestionTargets(targets) {
    if (!Array.isArray(targets) || targets.length > 8) throw new Error('Comparison target metadata is unavailable.');
    const ids = new Set();
    return targets.map(target => {
        if (!target || !label(target.id) || !label(target.name) || ids.has(target.id)) throw new Error('Comparison target metadata is unavailable.');
        ids.add(target.id);
        const catalogue = target.catalogue;
        if (catalogue === null || catalogue === undefined) return { id: target.id, name: target.name, catalogue: null };
        if (catalogue.appearance === undefined) return { id: target.id, name: target.name, catalogue: null };
        const a = catalogue.appearance;
        if (!a || !Array.isArray(a.families) || a.families.length === 0 || a.families.length > 3
            || new Set(a.families).size !== a.families.length || a.families.some(value => !['bright_star', 'double_star', 'deep_sky'].includes(value))
            || !Object.hasOwn(a, 'magnitude') || (a.magnitude !== null && !finite(a.magnitude)) || !label(a.magnitude_band)
            || ['magnitude_flag', 'magnitude_code'].some(key => !Object.hasOwn(a, key) || (a[key] !== null && !label(a[key])))
            || ['major_axis_arcmin', 'minor_axis_arcmin'].some(key => !Object.hasOwn(a, key) || (a[key] !== null && (!finite(a[key]) || a[key] < 0)))) throw new Error('Comparison target measurements are unavailable.');
        const source = {};
        for (const key of ['source', 'source_url', 'snapshot_sha256', 'attribution', 'license', 'license_url']) {
            if (typeof catalogue[key] !== 'string' || !catalogue[key].trim() || catalogue[key].length > 8192) throw new Error('Comparison source metadata is unavailable.');
            source[key] = catalogue[key];
        }
        if (!/^[a-f0-9]{64}$/.test(source.snapshot_sha256)) throw new Error('Comparison source identity is unavailable.');
        for (const key of ['source_url', 'license_url']) {
            let url;
            try { url = new URL(source[key]); } catch { throw new Error('Comparison source link is unavailable.'); }
            if (url.protocol !== 'https:' || url.username || url.password) throw new Error('Comparison source link is unavailable.');
        }
        return { id: target.id, name: target.name, catalogue: { ...source, appearance: {
            families: [...a.families], magnitude: a.magnitude, magnitude_band: a.magnitude_band,
            magnitude_flag: a.magnitude_flag, magnitude_code: a.magnitude_code,
            major_axis_arcmin: a.major_axis_arcmin, minor_axis_arcmin: a.minor_axis_arcmin,
        } } };
    });
}
export function catalogueDiameter(target) {
    const a = target?.catalogue?.appearance;
    // An absent/zero catalogue extent is not a measured point-source diameter.
    return a?.families.includes('deep_sky') && range(a.major_axis_arcmin, Number.MIN_VALUE, 10800) ? a.major_axis_arcmin : null;
}
