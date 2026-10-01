import { telescopeOptics, binocularOptics, angularComparison, opticalNumber } from './optics.js';

export function mountOptics(root, getEquipment) {
    if (!root) return { refresh() {}, dispose() {} };
    const doc = root.ownerDocument;
    const get = key => root.querySelector(`[data-optics-${key}]`);
    const instrument = get('instrument'), eyepiece = get('eyepiece'), accessory = get('accessory');
    let disposed = false;
    let previousInstrument = '';
    function fill(select, rows, label) {
        const selected = select.value;
        select.replaceChildren();
        const blank = doc.createElement('option');
        blank.value = ''; blank.textContent = label;
        select.append(blank);
        for (const row of rows) {
            const option = doc.createElement('option');
            option.value = row.id; option.textContent = row.name;
            select.append(option);
        }
        select.value = rows.some(row => row.id === selected) ? selected : '';
    }
    function inputNumber(input, label) {
        const text = input.value.trim();
        if (text === '') return null;
        if (!/^(?:[0-9]+\.?[0-9]*|\.[0-9]+)(?:[eE][+-]?[0-9]+)?$/.test(text) || !Number.isFinite(Number(text)) || Number(text) <= 0) throw new Error(`${label} must be a positive number, or blank if unknown.`);
        return Number(text);
    }
    function output(data) {
        get('focal').textContent = data.effectiveFocalLengthMm === null ? 'Not available' : `${opticalNumber(data.effectiveFocalLengthMm)} mm`;
        get('magnification').textContent = data.magnification === null ? 'Unknown' : `${opticalNumber(data.magnification)}×`;
        get('pupil').textContent = data.exitPupilMm === null ? 'Unknown' : `${opticalNumber(data.exitPupilMm)} mm`;
        get('field').textContent = data.trueFovDeg === null ? 'Unknown — a usable field stop or apparent field is needed for a telescope, or a stated true field for binoculars.' : `${opticalNumber(data.trueFovDeg)}° (${opticalNumber(data.trueFovDeg * 60)} arcminutes)`;
        get('method').textContent = {
            'field-stop': 'Field-stop estimate, using the effective field stop of the eyepiece.',
            'apparent-field': 'Approximation: apparent field divided by magnification. Eyepiece distortion can change the true field.',
            stated: 'Stated binocular true field entered below; not calculated from magnification.',
        }[data.fovMethod] ?? 'No field-of-view estimate is available.';
    }
    function clearComparison() {
        get('diagram').hidden = true;
        get('comparison').textContent = 'Enter an angular diameter and a known field of view to compare their sizes.';
    }
    function calculate() {
        if (disposed) return;
        const rows = getEquipment();
        const selected = rows.find(row => row.id === instrument.value);
        const instrumentSnapshot = JSON.stringify(selected ?? null);
        if (previousInstrument !== instrumentSnapshot) {
            // A stated field belongs to these binocular specifications, not
            // another selection or an imported/edited record reusing its ID.
            get('binocular-field').value = '';
            previousInstrument = instrumentSnapshot;
        }
        const isBinocular = selected?.kind === 'binocular';
        eyepiece.disabled = isBinocular;
        accessory.disabled = isBinocular;
        get('binocular-input').hidden = !isBinocular;
        get('binocular-field').disabled = !isBinocular;
        get('error').textContent = '';
        clearComparison();
        let data = telescopeOptics(null);
        try {
            const statedField = isBinocular ? inputNumber(get('binocular-field'), 'Binocular true field') : null;
            if (statedField !== null && statedField > 180) throw new Error('Binocular true field must be no greater than 180 degrees.');
            data = isBinocular ? binocularOptics(selected, statedField)
                : telescopeOptics(selected, rows.find(row => row.id === eyepiece.value) ?? null, rows.find(row => row.id === accessory.value) ?? null);
            output(data);
            const diameter = inputNumber(get('diameter'), 'Angular diameter');
            if (diameter !== null && diameter > 10800) throw new Error('Angular diameter must be no greater than 10,800 arcminutes (180 degrees).');
            const comparison = angularComparison(data.trueFovDeg, diameter);
            if (!comparison) return;
            get('field-circle').setAttribute('r', comparison.fieldRadius);
            get('target-circle').setAttribute('r', comparison.targetRadius);
            const tinyField = comparison.fieldRadius < 0.5, tinyTarget = comparison.targetRadius < 0.5;
            get('field-circle').setAttribute('visibility', tinyField ? 'hidden' : 'visible');
            get('target-circle').setAttribute('visibility', tinyTarget ? 'hidden' : 'visible');
            get('diagram').hidden = false;
            get('comparison').textContent = `Your entered diameter is ${opticalNumber(diameter)} arcminutes: ${opticalNumber(comparison.fieldFraction * 100)}% of the field diameter. ${comparison.fits ? 'It fits geometrically within this circular field.' : 'It is larger than this circular field.'} The solid circle is the field; the dashed circle is the entered diameter. This does not predict visibility or appearance.${tinyField || tinyTarget ? ' The smaller circle is below the diagram’s display resolution and is omitted; use the numeric ratio.' : ''}`;
            get('diagram-description').textContent = get('comparison').textContent;
        } catch (error) {
            output(data);
            get('error').textContent = error.message;
        }
    }
    function refresh() {
        if (disposed) return;
        const rows = getEquipment();
        fill(instrument, rows.filter(row => ['telescope', 'binocular'].includes(row.kind)), 'Choose a saved telescope or binoculars');
        fill(eyepiece, rows.filter(row => row.kind === 'eyepiece'), 'Choose a saved eyepiece');
        fill(accessory, rows.filter(row => ['barlow', 'reducer'].includes(row.kind)), 'No Barlow or reducer (1×)');
        calculate();
    }
    root.addEventListener('input', calculate);
    root.addEventListener('change', calculate);
    refresh();
    return { refresh, dispose() { disposed = true; root.removeEventListener('input', calculate); root.removeEventListener('change', calculate); } };
}
