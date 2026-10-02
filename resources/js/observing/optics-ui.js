import { telescopeOptics, binocularOptics, angularComparison, cameraOptics, cameraComparison, opticalNumber } from './optics.js';

export function mountOptics(root, getEquipment) {
    if (!root) return { refresh() {}, dispose() {} };
    const doc = root.ownerDocument;
    const get = key => root.querySelector(`[data-optics-${key}]`);
    const instrument = get('instrument'), eyepiece = get('eyepiece'), accessory = get('accessory'), camera = get('camera');
    let disposed = false;
    let previousInstrument = '';
    const cameraMode = () => get('mode').value === 'camera';
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
        get('pixel').textContent = 'Not applicable to visual mode';
        get('magnification').textContent = data.magnification === null ? 'Unknown' : `${opticalNumber(data.magnification)}×`;
        get('pupil').textContent = data.exitPupilMm === null ? 'Unknown' : `${opticalNumber(data.exitPupilMm)} mm`;
        get('field').textContent = data.trueFovDeg === null ? 'Unknown — a usable field stop or apparent field is needed for a telescope, or a stated true field for binoculars.' : `${opticalNumber(data.trueFovDeg)}° (${opticalNumber(data.trueFovDeg * 60)} arcminutes)`;
        get('method').textContent = {
            'field-stop': 'Field-stop estimate, using the effective field stop of the eyepiece.',
            'apparent-field': 'Approximation: apparent field divided by magnification. Eyepiece distortion can change the true field.',
            stated: 'Stated binocular true field entered below; not calculated from magnification.',
        }[data.fovMethod] ?? 'No field-of-view estimate is available.';
    }
    function outputCamera(data) {
        get('focal').textContent = data.effectiveFocalLengthMm === null ? 'Not available' : `${opticalNumber(data.effectiveFocalLengthMm)} mm`;
        get('magnification').textContent = 'Not applicable to camera projection';
        get('pupil').textContent = 'Not applicable to camera projection';
        get('pixel').textContent = data.pixelScaleArcsec === null ? 'Unknown — a telescope and camera with known pixel size are needed' : `${opticalNumber(data.pixelScaleArcsec)} arcseconds per pixel at the centre`;
        get('field').textContent = data.widthDeg === null || data.heightDeg === null ? 'Unknown — choose a telescope and camera with known sensor width and height.' : `${opticalNumber(data.widthDeg)}° wide × ${opticalNumber(data.heightDeg)}° high (${opticalNumber(data.widthDeg * 60)} × ${opticalNumber(data.heightDeg * 60)} arcminutes)`;
        get('method').textContent = 'Ideal rectilinear camera projection: each angle is 2 atan(sensor dimension / (2 × effective focal length)). This does not account for distortion, vignetting, focus or equipment compatibility.';
    }
    function clearComparison() {
        get('diagram').hidden = true;
        get('camera-diagram').hidden = true;
        get('comparison').textContent = 'Enter an angular diameter and a known field of view to compare their sizes.';
    }
    function drawVisual(data, diameter) {
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
    }
    function drawCamera(data, diameter) {
        const comparison = cameraComparison(data.widthDeg, data.heightDeg, diameter);
        if (!comparison) return;
        const rectangle = get('sensor-rectangle');
        for (const [key, value] of Object.entries({ x: -comparison.width / 2, y: -comparison.height / 2, width: comparison.width, height: comparison.height })) rectangle.setAttribute(key, value);
        get('camera-target').setAttribute('r', comparison.targetRadius);
        const tinySensor = Math.min(comparison.width, comparison.height) < 1, tinyTarget = comparison.targetRadius < 0.5;
        rectangle.setAttribute('visibility', tinySensor ? 'hidden' : 'visible');
        get('camera-target').setAttribute('visibility', tinyTarget ? 'hidden' : 'visible');
        get('camera-diagram').hidden = false;
        get('comparison').textContent = `Your entered diameter is ${opticalNumber(diameter)} arcminutes: ${opticalNumber(comparison.widthFraction * 100)}% of the angular width and ${opticalNumber(comparison.heightFraction * 100)}% of the angular height. ${comparison.fits ? 'A centred circular target fits geometrically within the rectangular field.' : 'A centred circular target extends beyond the rectangular field.'} The rectangle is the sensor field; the dashed circle is the entered angular diameter in a central rectilinear projection, not a sky image.${tinySensor || tinyTarget ? ' A shape below display resolution is omitted; use the numeric comparison.' : ''}`;
        get('camera-description').textContent = get('comparison').textContent;
    }
    function calculate() {
        if (disposed) return;
        const rows = getEquipment();
        const selected = rows.find(row => row.id === instrument.value);
        const instrumentSnapshot = JSON.stringify(selected ?? null);
        if (previousInstrument !== instrumentSnapshot) {
            get('binocular-field').value = '';
            previousInstrument = instrumentSnapshot;
        }
        const isCamera = cameraMode(), isBinocular = !isCamera && selected?.kind === 'binocular';
        eyepiece.disabled = isBinocular || isCamera;
        accessory.disabled = isBinocular;
        camera.disabled = !isCamera;
        get('camera-input').hidden = !isCamera;
        get('binocular-input').hidden = !isBinocular;
        get('binocular-field').disabled = !isBinocular;
        get('error').textContent = '';
        clearComparison();
        let data = isCamera ? cameraOptics(null, null) : telescopeOptics(null);
        const show = isCamera ? outputCamera : output;
        try {
            const amplifier = rows.find(row => row.id === accessory.value) ?? null;
            if (isCamera) data = cameraOptics(selected, rows.find(row => row.id === camera.value) ?? null, amplifier);
            else {
                // Magnification and exit pupil do not depend on a valid field.
                data = isBinocular ? binocularOptics(selected, null)
                    : telescopeOptics(selected, rows.find(row => row.id === eyepiece.value) ?? null, amplifier);
                if (isBinocular) {
                    const statedField = inputNumber(get('binocular-field'), 'Binocular true field');
                    if (statedField !== null && statedField > 180) throw new Error('Binocular true field must be no greater than 180 degrees.');
                    if (statedField !== null) data = binocularOptics(selected, statedField);
                }
            }
            show(data);
            const diameter = inputNumber(get('diameter'), 'Angular diameter');
            if (diameter !== null && (diameter > 10800 || (isCamera && diameter === 10800))) throw new Error(isCamera ? 'Camera projection needs a diameter smaller than 180 degrees (10,800 arcminutes).' : 'Angular diameter must be no greater than 10,800 arcminutes (180 degrees).');
            if (isCamera) drawCamera(data, diameter); else drawVisual(data, diameter);
        } catch (error) { show(data); get('error').textContent = error.message; }
    }
    function refresh() {
        if (disposed) return;
        const rows = getEquipment();
        fill(instrument, rows.filter(row => row.kind === 'telescope' || (!cameraMode() && row.kind === 'binocular')), cameraMode() ? 'Choose a saved telescope' : 'Choose a saved telescope or binoculars');
        fill(eyepiece, rows.filter(row => row.kind === 'eyepiece'), 'Choose a saved eyepiece');
        fill(accessory, rows.filter(row => ['barlow', 'reducer'].includes(row.kind)), 'No Barlow or reducer (1×)');
        fill(camera, rows.filter(row => row.kind === 'camera'), 'Choose a saved camera');
        calculate();
    }
    function changed(event) { if (event?.target === get('mode')) refresh(); else calculate(); }
    root.addEventListener('input', changed);
    root.addEventListener('change', changed);
    refresh();
    return { refresh, dispose() { disposed = true; root.removeEventListener('input', changed); root.removeEventListener('change', changed); } };
}
