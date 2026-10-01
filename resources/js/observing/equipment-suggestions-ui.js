import { loadWorkspace } from './workspace-store.js';
import { opticalNumber } from './optics.js';
import { compareEyepieces, temporaryTelescope, temporaryEyepiece, validateSuggestionTargets, catalogueDiameter } from './equipment-suggestions.js';

export function mountEquipmentSuggestions(root, { targets = [], storage = null } = {}) {
    if (!root) return { refresh() {}, dispose() {} };
    const doc = root.ownerDocument, get = key => root.querySelector(`[data-suggestions-${key}]`);
    let disposed = false, equipment = [], previousInstrument = '', cleanTargets = [];
    try { cleanTargets = validateSuggestionTargets(targets); }
    catch { get('metadata-error').textContent = 'Catalogue comparison metadata is unavailable. You can still enter an angular size yourself.'; }
    function fill(select, rows, blank) {
        const selected = select.value;
        select.replaceChildren();
        const option = doc.createElement('option'); option.value = ''; option.textContent = blank; select.append(option);
        for (const row of rows) { const option = doc.createElement('option'); option.value = row.id; option.textContent = row.name; select.append(option); }
        select.value = rows.some(row => row.id === selected) ? selected : '';
    }
    function number(key, optional = false) {
        const text = get(key).value.trim();
        if (optional && text === '') return null;
        if (!/^(?:[0-9]+\.?[0-9]*|\.[0-9]+)(?:[eE][+-]?[0-9]+)?$/.test(text) || !Number.isFinite(Number(text)) || Number(text) <= 0) throw new Error('Enter positive numbers for the active inputs; leave optional unknown values blank.');
        return Number(text);
    }
    function sourceLink(parent, text, href) { const link = doc.createElement('a'); link.textContent = text; link.href = href; link.className = 'underline'; parent.append(link); }
    function context(target, manual) {
        const node = get('context'); node.replaceChildren();
        const p = doc.createElement('p');
        if (manual) {
            const note = get('size-source').value.trim();
            if (note.length > 300) throw new Error('Keep your angular-size source note to 300 characters.');
            p.textContent = `User-entered angular diameter${note ? ` — ${note}` : '; source not specified'}. This temporary entry is not a catalogue measurement.`;
        } else if (!target?.catalogue) p.textContent = 'No sourced angular extent or photometric context is available for this selection. Planet diameters and double-star separations are not inferred.';
        else {
            const c = target.catalogue, a = c.appearance;
            const magnitude = a.magnitude === null ? 'not reported' : opticalNumber(a.magnitude);
            p.textContent = `Catalogue family: ${a.families.map(value => ({ bright_star: 'bright star', double_star: 'double star', deep_sky: 'deep sky' })[value]).join(', ')}. Recorded magnitude: ${magnitude}; band: ${a.magnitude_band}; uncertainty flag: ${a.magnitude_flag ?? 'none reported'}; source code: ${a.magnitude_code ?? 'none reported'}. Major / minor axes: ${a.major_axis_arcmin === null ? 'not reported' : `${opticalNumber(a.major_axis_arcmin)} arcmin`} / ${a.minor_axis_arcmin === null ? 'not reported' : `${opticalNumber(a.minor_axis_arcmin)} arcmin`}. ${catalogueDiameter(target) === null ? 'No usable extended-target diameter is supplied for a field comparison.' : 'The catalogue major axis is used for field containment only.'} Integrated magnitudes of extended objects are not point-source visibility limits; these different bands, sizes and source flags do not determine whether you will see detail.`;
            node.append(p);
            const credit = doc.createElement('p'); credit.className = 'mt-2 break-words'; credit.textContent = `${c.attribution} Snapshot ${c.snapshot_sha256}. `;
            sourceLink(credit, `${c.source} source`, c.source_url); credit.append(doc.createTextNode(' · ')); sourceLink(credit, c.license, c.license_url); node.append(credit);
            return;
        }
        node.append(p);
    }
    function calculate() {
        if (disposed) return;
        get('error').textContent = ''; get('results').replaceChildren(); get('summary').textContent = '';
        const temporary = get('mode').value === 'temporary';
        get('temporary-inputs').hidden = !temporary; get('temporary-inputs').disabled = !temporary; get('instrument').disabled = temporary;
        const manual = get('size-mode').value === 'manual';
        get('size-inputs').hidden = !manual; get('size-inputs').disabled = !manual;
        const addEye = get('add-eyepiece').checked;
        get('eyepiece-inputs').hidden = !addEye; get('eyepiece-inputs').disabled = !addEye;
        let instrument = temporary ? null : equipment.find(row => row.id === get('instrument').value) ?? null;
        const isBinocular = instrument?.kind === 'binocular';
        get('binocular-input').hidden = !isBinocular; get('binocular-field').disabled = !isBinocular;
        get('accessory').disabled = isBinocular; get('add-eyepiece').disabled = isBinocular;
        if (isBinocular) { get('eyepiece-inputs').hidden = true; get('eyepiece-inputs').disabled = true; }
        const snapshot = temporary ? 'temporary' : JSON.stringify(instrument);
        if (previousInstrument !== snapshot) { get('binocular-field').value = ''; previousInstrument = snapshot; }
        try {
            const target = cleanTargets.find(row => row.id === get('target').value) ?? null;
            context(target, manual);
            const diameter = manual ? number('diameter', true) : catalogueDiameter(target);
            if (temporary) instrument = temporaryTelescope(number('aperture'), number('focal'));
            if (!instrument) { get('summary').textContent = 'Choose a saved instrument or enter a temporary telescope to compare eyepieces.'; return; }
            const eyes = equipment.filter(row => row.kind === 'eyepiece');
            if (addEye && !isBinocular) eyes.push(temporaryEyepiece(number('eye-focal'), number('eye-afov', true), number('eye-stop', true)));
            const rows = compareEyepieces({ instrument, eyepieces: eyes, accessory: isBinocular ? null : equipment.find(row => row.id === get('accessory').value) ?? null,
                diameterArcmin: diameter, binocularFieldDeg: isBinocular ? number('binocular-field', true) : null, order: get('order').value });
            const reason = { 'lowest-power': 'Lowest calculated magnification first; a starting point for locating a target, not an optimum power.', 'highest-power': 'Highest calculated magnification first; greater power does not promise more detail.', 'widest-field': 'Widest known estimated field first; unknown fields appear last.' }[get('order').value];
            get('summary').textContent = rows.length ? `${rows.length} ${isBinocular ? 'binocular setup' : 'eyepiece choices'}. ${reason} Nothing here is saved or sent.` : 'No saved eyepieces are available. Add a temporary eyepiece below, or save equipment in your observatory.';
            for (const row of rows) {
                const item = doc.createElement('li'); item.className = 'surface p-4';
                const title = doc.createElement('h3'); title.className = 'font-semibold break-words'; title.textContent = row.name; item.append(title);
                const description = doc.createElement('p'); description.className = 'mt-2 text-sm';
                const unit = (value, suffix) => value === null ? 'unknown' : `${opticalNumber(value)}${suffix}`;
                description.textContent = `Magnification ${unit(row.magnification, '×')}; exit pupil ${unit(row.exitPupilMm, ' mm')}; true field ${unit(row.trueFovDeg, '°')}. ${({ 'field-stop': 'Paraxial effective-field-stop estimate.', 'apparent-field': 'Approximation using apparent field / magnification; distortion can change the true field.', stated: 'Stated binocular field entered for this instrument.' })[row.fovMethod] ?? 'A usable effective field stop, apparent field or stated binocular field is needed for field comparison.'}`;
                item.append(description);
                const fit = doc.createElement('p'); fit.className = 'mt-2 text-sm';
                fit.textContent = row.comparison ? `${opticalNumber(diameter)} arcmin is ${opticalNumber(row.comparison.fieldFraction * 100)}% of the field diameter: ${row.comparison.fits ? 'within' : 'larger than'} this estimated circular field. Geometric containment is not visibility or a guarantee of the usable field.` : 'Angular containment unknown: both a usable angular diameter and field estimate are required.';
                item.append(fit); get('results').append(item);
            }
        } catch (error) { get('error').textContent = error.message; }
    }
    function refresh() {
        if (disposed) return;
        get('storage-error').textContent = '';
        try { equipment = loadWorkspace(storage ?? doc.defaultView.localStorage).equipment; }
        catch { equipment = []; get('storage-error').textContent = 'Saved equipment could not be read. Nothing was changed; temporary inputs still work.'; }
        fill(get('instrument'), equipment.filter(row => ['telescope', 'binocular'].includes(row.kind)), 'Choose a saved instrument');
        fill(get('accessory'), equipment.filter(row => ['barlow', 'reducer'].includes(row.kind)), 'No Barlow or reducer (1×)');
        calculate();
    }
    const changed = () => calculate(), clicked = event => { if (event.target === get('reload')) refresh(); };
    root.addEventListener('change', changed); root.addEventListener('input', changed); root.addEventListener('click', clicked);
    fill(get('target'), cleanTargets, 'No target selected');
    get('controls').disabled = false;
    refresh();
    return { refresh, dispose() { if (disposed) return; disposed = true; root.removeEventListener('change', changed); root.removeEventListener('input', changed); root.removeEventListener('click', clicked); get('controls').disabled = true; } };
}
