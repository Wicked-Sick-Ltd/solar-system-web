<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Reads a location the visitor pasted into the observer panel. Accepts what
 * people actually copy: a decimal pair ("51.51, -0.13", "51.51 N 0.13 W"),
 * degrees-minutes-seconds as Google Maps shows them (51°30′26″N 0°07′39″W),
 * or a Google Maps link that carries coordinates (@lat,lon / ?q= / ?ll= /
 * ?query= / !3d…!4d…). Results are rounded to 2 dp (~1 km), which is all the
 * sky calculation needs and all we want to know about the visitor.
 *
 * what3words addresses are recognised separately (what3words()) because they
 * need a network lookup the caller must decide to make.
 */
final class LocationParser
{
    private const DEC = '[-+]?\d{1,3}(?:\.\d+)?';

    /** Common last labels of a hostname, so a bare "a.b.com" is not read as three words. */
    private const TLDS = ['com', 'net', 'org', 'edu', 'gov', 'mil', 'int', 'io', 'co', 'uk', 'us', 'eu', 'de', 'fr', 'es', 'it', 'nl', 'ca', 'au', 'nz', 'app', 'dev', 'info', 'biz', 'me', 'tv', 'ai'];

    /** @return array{lat: float, lon: float}|null */
    public static function parse(string $text): ?array
    {
        $text = trim($text);
        if ($text === '' || self::what3words($text) !== null) {
            return null;
        }

        return self::fromUrl($text) ?? self::fromDms($text) ?? self::fromDecimal($text);
    }

    /** The three words of a what3words address (lower-cased, no slashes), or null. */
    public static function what3words(string $text): ?string
    {
        $text = trim($text);
        // A bare "///a.b.c", a what3words.com link, or three dot-joined words
        // with no digits. Words may be non-ASCII (what3words is multilingual).
        if (! preg_match('~^(?:https?://(?:www\.)?what3words\.com/|///)?(\p{L}+)\.(\p{L}+)\.(\p{L}+)$~u', $text, $m)) {
            return null;
        }
        // "www.google.com" is also three dot-joined words. Without the ///
        // prefix, refuse anything shaped like a hostname.
        $bare = ! str_starts_with($text, '///') && ! str_contains($text, 'what3words.com/');
        if ($bare && (mb_strtolower($m[1]) === 'www' || in_array(mb_strtolower($m[3]), self::TLDS, true))) {
            return null;
        }

        return mb_strtolower("{$m[1]}.{$m[2]}.{$m[3]}");
    }

    /** @return array{lat: float, lon: float}|null */
    private static function fromUrl(string $text): ?array
    {
        if (! preg_match('~^https?://~i', $text)) {
            return null;
        }
        $d = self::DEC;
        $patterns = [
            "~/@($d),($d)~",                              // …/maps/@51.5,-0.12,15z
            "~!3d($d)!4d($d)~",                           // …/data=…!3d51.5!4d-0.12
            "~[?&](?:q|ll|query|center|destination)=($d)(?:,|%2C)($d)~i",
        ];
        foreach ($patterns as $p) {
            if (preg_match($p, $text, $m)) {
                return self::pair((float) $m[1], (float) $m[2]);
            }
        }

        return null;
    }

    /** @return array{lat: float, lon: float}|null */
    private static function fromDms(string $text): ?array
    {
        // 51°30'26"N 0°07'39"W — any of the usual quote characters, optional seconds.
        $dms = '(\d{1,3})\s*[°º]\s*(\d{1,2})\s*[\'′’]\s*(?:(\d{1,2}(?:\.\d+)?)\s*["″”]\s*)?([NSEW])';
        if (! preg_match("~^$dms\s*,?\s*$dms$~iu", $text, $m, PREG_UNMATCHED_AS_NULL)) {
            return null;
        }
        $a = self::dmsToDecimal((float) $m[1], (float) $m[2], (float) ($m[3] ?? 0), strtoupper($m[4]));
        $b = self::dmsToDecimal((float) $m[5], (float) $m[6], (float) ($m[7] ?? 0), strtoupper($m[8]));

        return self::orient($a, strtoupper($m[4]), $b, strtoupper($m[8]));
    }

    /** @return array{lat: float, lon: float}|null */
    private static function fromDecimal(string $text): ?array
    {
        $d = self::DEC;
        $h = '([NSEW])?';
        // Optional "lat"/"lon" labels, optional hemisphere letter before or after each number.
        $num = "(?:lat(?:itude)?|lon(?:gitude)?|lng)?\s*:?\s*$h\s*($d)\s*$h";
        if (! preg_match("~^$num\s*(?:[,;]\s*|\s+)$num$~iu", $text, $m, PREG_UNMATCHED_AS_NULL)) {
            return null;
        }
        [$h1, $v1, $h2, $h3, $v2, $h4] = [$m[1] ?? '', (float) $m[2], $m[3] ?? '', $m[4] ?? '', (float) $m[5], $m[6] ?? ''];
        // "N 51.51 W 0.13": the regex reads W as the first number's suffix. When
        // both numbers use prefixes, the stray suffix is really the second prefix.
        if ($h1 !== '' && $h2 !== '' && $h3 === '' && $h4 === '') {
            [$h2, $h3] = ['', $h2];
        }
        $s1 = strtoupper($h1 !== '' ? $h1 : $h2);
        $s2 = strtoupper($h3 !== '' ? $h3 : $h4);
        if (in_array($s1, ['S', 'W'], true)) {
            $v1 = -abs($v1);
        }
        if (in_array($s2, ['S', 'W'], true)) {
            $v2 = -abs($v2);
        }

        return self::orient($v1, $s1, $v2, $s2);
    }

    private static function dmsToDecimal(float $deg, float $min, float $sec, string $hemi): float
    {
        $v = $deg + $min / 60 + $sec / 3600;

        return in_array($hemi, ['S', 'W'], true) ? -$v : $v;
    }

    /**
     * Decide which number is latitude. Hemisphere letters settle it; otherwise
     * the order is lat, lon (the convention Google Maps and GPS receivers use).
     *
     * @return array{lat: float, lon: float}|null
     */
    private static function orient(float $a, string $ha, float $b, string $hb): ?array
    {
        if (in_array($ha, ['E', 'W'], true) || in_array($hb, ['N', 'S'], true)) {
            [$a, $b] = [$b, $a];
        }

        return self::pair($a, $b);
    }

    /** @return array{lat: float, lon: float}|null */
    private static function pair(float $lat, float $lon): ?array
    {
        if ($lat < -90 || $lat > 90 || $lon < -180 || $lon > 180) {
            return null;
        }

        return ['lat' => round($lat, 2), 'lon' => round($lon, 2)];
    }
}
