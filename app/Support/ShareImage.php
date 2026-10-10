<?php

declare(strict_types=1);

namespace App\Support;

use Carbon\CarbonInterface;

/** Shared image URLs and disk namespaces change together when branding changes. */
final class ShareImage
{
    /** Committed fallback card, served whenever a live render isn't possible. */
    public const DEFAULT_PATH = 'images/og-public-universe.png';

    public const WIDTH = 1200;

    public const HEIGHT = 630;

    public static function version(): string
    {
        return substr(hash('sha256', implode("\0", [
            (string) config('site.name'),
            (string) config('site.tagline'),
            (string) config('og.version'),
        ])), 0, 20);
    }

    public static function objectUrl(string $id): string
    {
        return route('og.object', ['slug' => $id, 'v' => self::version()]);
    }

    public static function todayUrl(CarbonInterface $date): string
    {
        return route('og.today', ['date' => $date->format('Y-m-d'), 'v' => self::version()]);
    }

    /** The live site card, which carries the current catalogue counts. */
    public static function defaultUrl(): string
    {
        return route('og.site', ['v' => self::version()]);
    }

    /** The canonical host as printed on cards, e.g. "publicuniverse.net". */
    public static function domain(): ?string
    {
        $host = parse_url((string) config('app.url'), PHP_URL_HOST);

        return is_string($host) && $host !== '' ? preg_replace('/^www\./', '', $host) : null;
    }

    /** A canonical URL without its scheme, for printing on a card. */
    public static function printable(string $url): string
    {
        return (string) preg_replace('#^https?://(www\.)?#', '', Links::canonical($url));
    }
}
