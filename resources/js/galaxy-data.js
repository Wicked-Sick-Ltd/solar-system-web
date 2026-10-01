export const PARSEC_TO_LY = 3.261563777;
export const SUN_GALACTOCENTRIC = [-Math.sqrt(8122 ** 2 - 20.8 ** 2), 20.8, 0];

// Render with Galactic north up (+Y); the third axis is negated to retain handedness.
export function position(host, mode) {
    return mode === 'galaxy'
        ? [host.galactocentric_x_pc, host.galactocentric_z_pc, -host.galactocentric_y_pc]
        : [host.x_pc, host.z_pc, -host.y_pc];
}

export function visibleHosts(hosts, radius) {
    return hosts.filter(host => Number.isFinite(host.distance_pc) && host.distance_pc > 0
        && ['x_pc', 'y_pc', 'z_pc', 'galactocentric_x_pc', 'galactocentric_y_pc', 'galactocentric_z_pc'].every(key => Number.isFinite(host[key]))
        && (radius === 'all' || host.distance_pc <= Number(radius)));
}

// Keep small errors nonzero and preserve an unknown side instead of inventing it.
export function distanceUncertainty(host, locale) {
    const error = (value, sign) => {
        if (!Number.isFinite(value)) return 'not reported';
        const ly = Math.abs(value) * PARSEC_TO_LY;
        const text = new Intl.NumberFormat(locale, {
            maximumSignificantDigits: 4,
            notation: ly !== 0 && (ly < 0.01 || ly >= 1e6) ? 'scientific' : 'standard',
        }).format(ly);
        return `${sign}${text} light-years`;
    };
    return `Distance uncertainty: upper ${error(host.distance_error_plus_pc, '+')}; lower ${error(host.distance_error_minus_pc, '−')}.`;
}
