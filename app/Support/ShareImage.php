<?php

declare(strict_types=1);

namespace App\Support;

/** Shared image URLs and disk namespaces change together when branding changes. */
final class ShareImage
{
    public const DEFAULT_PATH = 'images/og-public-universe.png';

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

    public static function defaultUrl(): string
    {
        return asset(self::DEFAULT_PATH).'?v='.self::version();
    }
}
