<?php

declare(strict_types=1);

namespace App\Support;

/**
 * schema.org JSON-LD builders shared by the data hubs, the plugin page and
 * the handouts. Pages pass the result to Seo::jsonLd(), which rewrites every
 * on-site URL onto the canonical host.
 */
final class Schema
{
    public const DATA_LICENCE = 'https://github.com/Wicked-Sick-Ltd/solar-system-db/blob/main/LICENSE';

    /** Upstream catalogues the published database is derived from. */
    public const SOURCES = [
        ['@type' => 'Dataset', 'name' => 'JPL Horizons and Small-Body Database', 'url' => 'https://ssd.jpl.nasa.gov/'],
        ['@type' => 'Dataset', 'name' => 'NASA Exoplanet Archive', 'url' => 'https://exoplanetarchive.ipac.caltech.edu/'],
        ['@type' => 'Dataset', 'name' => 'IAU Minor Planet Center', 'url' => 'https://minorplanetcenter.net/'],
    ];

    /** @return array<string,mixed> */
    public static function creator(): array
    {
        return [
            '@type' => 'Organization',
            'name' => (string) config('site.name'),
            'legalName' => (string) config('site.operator'),
            'url' => Links::canonical(url('/')),
        ];
    }

    /**
     * A schema.org/Dataset for Google Dataset Search: the whole catalogue, or
     * one subset of it (planets, moons, asteroids, comets, TNOs, exoplanets).
     *
     * @return array<string,mixed>
     */
    public static function dataset(string $name, string $description, string $url, ?string $keywords = null): array
    {
        $schema = [
            '@context' => 'https://schema.org',
            '@type' => 'Dataset',
            'name' => $name,
            'description' => $description,
            'url' => $url,
            'license' => self::DATA_LICENCE,
            'isAccessibleForFree' => true,
            'creator' => self::creator(),
            'isBasedOn' => self::SOURCES,
            'distribution' => [
                [
                    '@type' => 'DataDownload',
                    'name' => __('Nightly SQLite database (manifest with checksums)'),
                    'encodingFormat' => 'application/json',
                    'contentUrl' => (string) config('site.download_url'),
                ],
                [
                    '@type' => 'DataDownload',
                    'name' => __('Read-only REST API'),
                    'encodingFormat' => 'application/json',
                    'contentUrl' => Links::cataloguePublicBase(),
                ],
            ],
        ];
        if ($keywords !== null) {
            $schema['keywords'] = $keywords;
        }

        return $schema;
    }

    /**
     * The whole catalogue as one Dataset, for the home, about and API pages.
     *
     * @return array<string,mixed>
     */
    public static function catalogue(string $url): array
    {
        return self::dataset(
            __(':site solar-system and exoplanet catalogue', ['site' => config('site.name')]),
            __('Over 1.5 million solar-system objects (planets, moons, dwarf planets, asteroids, comets, trans-Neptunian objects) plus confirmed exoplanets and their host stars, with orbital, physical and discovery data from NASA/JPL, the NASA Exoplanet Archive and the IAU Minor Planet Center. Published nightly as a SQLite database and served by a read-only REST API.'),
            $url,
            'astronomy, solar system, asteroids, comets, exoplanets, orbital elements, open data',
        );
    }

    /** @return array<string,mixed> */
    public static function webSite(): array
    {
        return [
            '@context' => 'https://schema.org',
            '@type' => 'WebSite',
            'name' => config('site.name'),
            'url' => route('home'),
            'description' => config('site.description'),
            'potentialAction' => [
                '@type' => 'SearchAction',
                'target' => route('search').'?q={query}',
                'query-input' => 'required name=query',
            ],
        ];
    }

    /** @return array<string,mixed> */
    public static function webApi(): array
    {
        return [
            '@context' => 'https://schema.org',
            '@type' => 'WebAPI',
            'name' => __(':site catalogue API', ['site' => config('site.name')]),
            'description' => __('Read-only JSON API and MCP server over the solar-system and exoplanet catalogue.'),
            'url' => Links::cataloguePublicBase(),
            'documentation' => Links::apiDocs(),
            'provider' => self::creator(),
            'isAccessibleForFree' => true,
        ];
    }

    /**
     * @param  list<string>  $clients
     * @return array<string,mixed>
     */
    public static function softwareApplication(string $name, string $description, string $url, array $clients): array
    {
        return [
            '@context' => 'https://schema.org',
            '@type' => 'SoftwareApplication',
            'name' => $name,
            'description' => $description,
            'url' => $url,
            'applicationCategory' => 'DeveloperApplication',
            'operatingSystem' => 'Any',
            'softwareRequirements' => __('An MCP-compatible client: :clients', ['clients' => implode(', ', $clients)]),
            'keywords' => implode(', ', $clients),
            'offers' => ['@type' => 'Offer', 'price' => '0', 'priceCurrency' => 'GBP'],
            'isAccessibleForFree' => true,
            'author' => self::creator(),
        ];
    }

    /** @return array<string,mixed> */
    public static function learningResource(string $name, string $description, string $url, string $level, string $type = 'activity'): array
    {
        return [
            '@context' => 'https://schema.org',
            '@type' => 'LearningResource',
            'name' => $name,
            'description' => $description,
            'url' => $url,
            'learningResourceType' => $type,
            'educationalLevel' => $level,
            'encodingFormat' => 'text/html',
            'isAccessibleForFree' => true,
            'inLanguage' => 'en-GB',
            'provider' => self::creator(),
        ];
    }
}
