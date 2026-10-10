<?php

declare(strict_types=1);

use App\Services\Sitemap\IndexableUrl;
use App\Services\Sitemap\SitemapXml;

it('splits a urlset before it passes 50k urls or 50MB', function () {
    $entries = [];
    for ($i = 0; $i < 5; $i++) {
        $entries[] = ['loc' => 'https://publicuniverse.test/objects/body-'.$i, 'lastmod' => '2026-10-01T12:00:00Z', 'priority' => '0.6'];
    }

    $chunks = SitemapXml::chunks($entries, 2, SitemapXml::MAX_BYTES);

    expect($chunks)->toHaveCount(3)
        ->and($chunks[0])->toHaveCount(2)
        ->and($chunks[1])->toHaveCount(2)
        ->and($chunks[2])->toHaveCount(1);

    $row = strlen(SitemapXml::url($entries[0]));
    $bySize = SitemapXml::chunks($entries, SitemapXml::MAX_URLS, strlen(SitemapXml::urlset([])) + $row);
    expect($bySize)->toHaveCount(5);

    $document = SitemapXml::urlset($chunks[0]);
    expect(substr_count($document, '<url>'))->toBe(2)
        ->and($document)->toContain('<loc>https://publicuniverse.test/objects/body-0</loc><lastmod>2026-10-01T12:00:00Z</lastmod><priority>0.6</priority>');
});

it('round-trips catalogue urls and drops ones the router would not serve', function () {
    config(['app.url' => 'https://publicuniverse.test']);

    expect(IndexableUrl::forRoute('objects.show', ['slug' => 'moon-s/2019-s-1']))
        ->toBe('https://publicuniverse.test/objects/moon-s/2019-s-1')
        ->and(IndexableUrl::forRoute('objects.show', ['slug' => 'not a slug']))->toBeNull()
        ->and(IndexableUrl::forRoute('exoplanets.show', ['id' => 'exo-proxima-b']))
        ->toBe('https://publicuniverse.test/exoplanets/exo-proxima-b')
        ->and(IndexableUrl::forRoute('exoplanets.show', ['id' => 'exo/slash']))->toBeNull()
        ->and(IndexableUrl::forRoute('systems.show', ['id' => 'host/slash']))->toBeNull()
        ->and(IndexableUrl::forRoute('home'))->toBe('https://publicuniverse.test');
});
