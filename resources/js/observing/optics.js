// Geometric estimates only. Sources and model limits: docs/observing-optics.md.
const positive = value => typeof value === 'number' && Number.isFinite(value) && value > 0;
const result = () => ({ effectiveFocalLengthMm: null, magnification: null, exitPupilMm: null, trueFovDeg: null, fovMethod: null });
function usable(value) { return positive(value) ? value : null; }

export function telescopeOptics(telescope, eyepiece = null, accessory = null) {
    const out = result();
    if (telescope?.kind !== 'telescope' || !positive(telescope.focalLengthMm)) return out;
    let factor = 1;
    if (accessory !== null) {
        if (!['barlow', 'reducer'].includes(accessory?.kind) || !positive(accessory.factor)
            || (accessory.kind === 'barlow' && accessory.factor < 1)
            || (accessory.kind === 'reducer' && accessory.factor > 1)) return out;
        factor = accessory.factor;
    }
    out.effectiveFocalLengthMm = usable(telescope.focalLengthMm * factor);
    if (out.effectiveFocalLengthMm === null || eyepiece?.kind !== 'eyepiece') return out;
    if (positive(eyepiece.focalLengthMm)) {
        out.magnification = usable(out.effectiveFocalLengthMm / eyepiece.focalLengthMm);
        if (positive(telescope.apertureMm) && out.magnification !== null) out.exitPupilMm = usable(telescope.apertureMm / out.magnification);
    }
    if (positive(eyepiece.fieldStopMm)) {
        // Published effective field stop is preferred; this is still a
        // paraxial estimate, not ray tracing or a compatibility guarantee.
        const field = eyepiece.fieldStopMm / out.effectiveFocalLengthMm * 180 / Math.PI;
        if (positive(field) && field <= 180) { out.trueFovDeg = field; out.fovMethod = 'field-stop'; }
    } else if (eyepiece.fieldStopMm === null || eyepiece.fieldStopMm === undefined) {
        // Invalid non-null field stops must not silently use a different model.
        if (positive(eyepiece.apparentFovDeg) && eyepiece.apparentFovDeg <= 180 && out.magnification !== null) {
            const field = eyepiece.apparentFovDeg / out.magnification;
            if (positive(field) && field <= 180) { out.trueFovDeg = field; out.fovMethod = 'apparent-field'; }
        }
    }
    return out;
}
export function binocularOptics(binocular, statedTrueFovDeg = null) {
    const out = result();
    if (binocular?.kind !== 'binocular' || !positive(binocular.magnification)) return out;
    out.magnification = binocular.magnification;
    if (positive(binocular.apertureMm)) out.exitPupilMm = usable(binocular.apertureMm / binocular.magnification);
    if (positive(statedTrueFovDeg) && statedTrueFovDeg <= 180) { out.trueFovDeg = statedTrueFovDeg; out.fovMethod = 'stated'; }
    return out;
}
export function angularComparison(trueFovDeg, diameterArcmin) {
    if (!positive(trueFovDeg) || trueFovDeg > 180 || !positive(diameterArcmin) || diameterArcmin > 10800) return null;
    const diameterDeg = diameterArcmin / 60;
    const ratio = diameterDeg / trueFovDeg;
    if (!positive(ratio)) return null;
    // Scale both circles together so their ratio stays honest, whether the
    // target is smaller or larger than the field. Never enlarge a tiny target.
    return {
        diameterDeg, fieldFraction: ratio, fits: ratio <= 1,
        fieldRadius: 80 / Math.max(1, ratio), targetRadius: 80 * Math.min(1, ratio),
    };
}
export function opticalNumber(value) {
    if (value === null || !Number.isFinite(value)) return 'Unknown';
    if (value === 0) return '0';
    // Significant figures prevent small positive angles being displayed as 0.
    return value < 0.001 || value >= 100000 ? value.toExponential(3) : Number(value.toPrecision(4)).toString();
}

export function cameraOptics(telescope, camera, accessory = null) {
    const focal = telescopeOptics(telescope, null, accessory).effectiveFocalLengthMm;
    const out = { effectiveFocalLengthMm: focal, widthDeg: null, heightDeg: null, pixelScaleArcsec: null };
    if (focal === null || camera?.kind !== 'camera') return out;
    const angle = dimension => {
        const degrees = 2 * Math.atan(dimension / (2 * focal)) * 180 / Math.PI;
        return positive(degrees) && degrees < 180 ? degrees : null;
    };
    if (positive(camera.sensorWidthMm)) out.widthDeg = usable(angle(camera.sensorWidthMm));
    if (positive(camera.sensorHeightMm)) out.heightDeg = usable(angle(camera.sensorHeightMm));
    // Angular width of one pixel centred on the optical axis, not a global
    // scale or a resolving-power claim. micrometres -> millimetres -> arcseconds.
    if (positive(camera.pixelSizeUm) && positive(camera.sensorWidthMm) && positive(camera.sensorHeightMm)
        && camera.pixelSizeUm / 1000 <= Math.min(camera.sensorWidthMm, camera.sensorHeightMm)) {
        const pixelAngle = angle(camera.pixelSizeUm / 1000);
        if (pixelAngle !== null) out.pixelScaleArcsec = usable(pixelAngle * 3600);
    }
    return out;
}
export function cameraComparison(widthDeg, heightDeg, diameterArcmin) {
    if (!positive(widthDeg) || widthDeg >= 180 || !positive(heightDeg) || heightDeg >= 180
        || !positive(diameterArcmin) || diameterArcmin >= 10800) return null;
    const diameterDeg = diameterArcmin / 60;
    // A central rectilinear projection, preserving sensor aspect ratio even
    // at wide fields. A centred angular circle projects to a circle.
    const halfWidth = Math.tan(widthDeg * Math.PI / 360);
    const halfHeight = Math.tan(heightDeg * Math.PI / 360);
    const target = Math.tan(diameterDeg * Math.PI / 360);
    const scale = Math.max(halfWidth, halfHeight, target);
    if (!positive(scale)) return null;
    return {
        width: 160 * halfWidth / scale, height: 160 * halfHeight / scale,
        targetRadius: 80 * target / scale, fits: diameterDeg <= Math.min(widthDeg, heightDeg),
        widthFraction: diameterDeg / widthDeg, heightFraction: diameterDeg / heightDeg,
    };
}
