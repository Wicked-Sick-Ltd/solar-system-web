<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Official social profiles from config('site.social'). Unset networks are
 * dropped so links and JSON-LD only ever name accounts that exist.
 */
final class Social
{
    public const LABELS = [
        'x' => 'X',
        'bluesky' => 'Bluesky',
        'instagram' => 'Instagram',
        'threads' => 'Threads',
        'tiktok' => 'TikTok',
        'youtube' => 'YouTube',
        'facebook' => 'Facebook',
    ];

    /** @return array<string,string> network => https URL, in config order */
    public static function profiles(): array
    {
        $profiles = [];
        foreach ((array) config('site.social', []) as $network => $url) {
            if (is_string($network) && is_string($url) && str_starts_with(trim($url), 'https://')) {
                $profiles[$network] = trim($url);
            }
        }

        return $profiles;
    }

    public static function label(string $network): string
    {
        return self::LABELS[$network] ?? ucfirst($network);
    }

    /** @return array<string,mixed> */
    public static function organizationSchema(): array
    {
        $schema = [
            '@context' => 'https://schema.org',
            '@type' => 'Organization',
            'name' => (string) config('site.name'),
            'url' => Links::canonical(url('/')),
        ];
        $profiles = array_values(self::profiles());
        if ($profiles !== []) {
            $schema['sameAs'] = $profiles;
        }

        return $schema;
    }
}
