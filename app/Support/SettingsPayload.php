<?php

declare(strict_types=1);

namespace App\Support;

/**
 * The settings-share token: everything the site remembers about a visitor,
 * encoded so they can carry it to another browser or device without an
 * account. The token is placed in the URL fragment (`#s=…`) and decoded in
 * the browser; it must never be put on the query string, which would reach
 * the server, access logs and Referer. This class is the codec spec the
 * settings page's JavaScript mirrors.
 *
 * Shape: {"theme": "dark"|"light", "location": {"lat": float, "lon": float},
 *         "preferences": {"timeFormat": "auto"|"12"|"24"}}. Every key optional.
 */
final class SettingsPayload
{
    public const array THEMES = ['dark', 'light'];

    public const array TIME_FORMATS = ['auto', '12', '24'];

    /** @param array<string,mixed> $settings */
    public static function encode(array $settings): string
    {
        $json = json_encode(self::clean($settings), JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);

        return rtrim(strtr(base64_encode($json), '+/', '-_'), '=');
    }

    /**
     * Decode a share token. Returns only the keys that validate; null when the
     * token is unreadable or holds nothing usable.
     *
     * @return array<string,mixed>|null
     */
    public static function decode(string $token): ?array
    {
        $token = trim($token);
        if ($token === '' || strlen($token) > 400 || ! preg_match('~^[A-Za-z0-9_-]+$~', $token)) {
            return null;
        }
        $json = base64_decode(strtr($token, '-_', '+/'), true);
        if ($json === false) {
            return null;
        }
        $data = json_decode($json, true);
        if (! is_array($data)) {
            return null;
        }
        $clean = self::clean($data);

        return $clean === [] ? null : $clean;
    }

    /**
     * Keep only known keys with valid values.
     *
     * @param  array<string,mixed>  $data
     * @return array<string,mixed>
     */
    public static function clean(array $data): array
    {
        $out = [];

        if (isset($data['theme']) && in_array($data['theme'], self::THEMES, true)) {
            $out['theme'] = $data['theme'];
        }

        $loc = $data['location'] ?? null;
        if (is_array($loc) && is_numeric($loc['lat'] ?? null) && is_numeric($loc['lon'] ?? null)) {
            $lat = round((float) $loc['lat'], 2);
            $lon = round((float) $loc['lon'], 2);
            if ($lat >= -90 && $lat <= 90 && $lon >= -180 && $lon <= 180) {
                $out['location'] = ['lat' => $lat, 'lon' => $lon];
            }
        }

        $prefs = $data['preferences'] ?? null;
        if (is_array($prefs)) {
            $p = [];
            if (isset($prefs['timeFormat']) && in_array((string) $prefs['timeFormat'], self::TIME_FORMATS, true)) {
                $p['timeFormat'] = (string) $prefs['timeFormat'];
            }
            if ($p !== []) {
                $out['preferences'] = $p;
            }
        }

        return $out;
    }
}
