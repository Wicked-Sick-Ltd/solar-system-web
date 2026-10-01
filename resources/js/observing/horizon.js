export const MAX_HORIZON_POINTS = 72;
const finite = value => typeof value === 'number' && Number.isFinite(value);

export function validateHorizonMask(value) {
    if (value === null) return null;
    if (!Array.isArray(value) || value.length < 2 || value.length > MAX_HORIZON_POINTS) throw new Error('A horizon mask needs 2 to 72 distinct azimuth measurements, or leave it unknown.');
    const points = value.map(point => {
        if (!point || typeof point !== 'object' || Array.isArray(point)
            || Object.keys(point).length !== 2 || !Object.hasOwn(point, 'azimuthDeg') || !Object.hasOwn(point, 'minAltitudeDeg')) throw new Error('Each horizon point needs only azimuthDeg and minAltitudeDeg.');
        if (!finite(point.azimuthDeg) || point.azimuthDeg < 0 || point.azimuthDeg > 360) throw new Error('Horizon azimuth must be from 0 to 360 degrees.');
        if (!finite(point.minAltitudeDeg) || point.minAltitudeDeg < -90 || point.minAltitudeDeg > 90) throw new Error('Horizon altitude must be from −90 to 90 degrees.');
        return { azimuthDeg: point.azimuthDeg === 360 ? 0 : point.azimuthDeg, minAltitudeDeg: point.minAltitudeDeg };
    }).sort((a, b) => a.azimuthDeg - b.azimuthDeg);
    if (new Set(points.map(point => point.azimuthDeg)).size !== points.length) throw new Error('Horizon azimuths must be distinct; 0 and 360 are the same direction.');
    return points;
}
export function parseHorizonText(text) {
    if (typeof text !== 'string' || text.length > 8000) throw new Error('Horizon text must be no more than 8,000 characters.');
    if (!text.trim()) return null;
    const numeric = /^[+-]?(?:[0-9]+\.?[0-9]*|\.[0-9]+)(?:[eE][+-]?[0-9]+)?$/;
    const points = text.trim().split(/\r?\n/).map(line => {
        const values = line.split(',').map(value => value.trim());
        if (values.length !== 2 || values.some(value => !numeric.test(value))) throw new Error('Use one azimuth, altitude pair per line, with a comma and decimal points.');
        return { azimuthDeg: Number(values[0]), minAltitudeDeg: Number(values[1]) };
    });
    return validateHorizonMask(points);
}
export function horizonText(mask) {
    return mask === null ? '' : validateHorizonMask(mask).map(point => `${point.azimuthDeg}, ${point.minAltitudeDeg}`).join('\n');
}
export function horizonAltitude(mask, azimuthDeg) {
    if (!finite(azimuthDeg) || azimuthDeg < 0 || azimuthDeg > 360) throw new Error('Direction must be from 0 to 360 degrees.');
    const points = validateHorizonMask(mask);
    if (points === null) return null;
    const azimuth = azimuthDeg === 360 ? 0 : azimuthDeg;
    const match = points.find(point => point.azimuthDeg === azimuth);
    if (match) return match.minAltitudeDeg;
    const upperIndex = points.findIndex(point => point.azimuthDeg > azimuth);
    const upper = upperIndex === -1 ? { ...points[0], azimuthDeg: points[0].azimuthDeg + 360 } : points[upperIndex];
    const lower = upperIndex === 0 ? { ...points.at(-1), azimuthDeg: points.at(-1).azimuthDeg - 360 }
        : points[upperIndex === -1 ? points.length - 1 : upperIndex - 1];
    const fraction = (azimuth - lower.azimuthDeg) / (upper.azimuthDeg - lower.azimuthDeg);
    return lower.minAltitudeDeg + fraction * (upper.minAltitudeDeg - lower.minAltitudeDeg);
}
export function minimumAltitudeAt(site, azimuthDeg) {
    if (!finite(site?.minAltitudeDeg) || site.minAltitudeDeg < 0 || site.minAltitudeDeg > 90) throw new Error('Site baseline altitude must be from 0 to 90 degrees.');
    const horizon = horizonAltitude(site.horizonMask ?? null, azimuthDeg);
    return { minimumAltitudeDeg: Math.max(site.minAltitudeDeg, horizon ?? site.minAltitudeDeg), horizonKnown: horizon !== null };
}
