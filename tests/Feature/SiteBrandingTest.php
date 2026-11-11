<?php

declare(strict_types=1);

beforeEach(fn () => fakeSolar());

it('uses the public name in metadata and contact links independently of the application name', function () {
    config(['app.name' => 'Solar', 'site.name' => 'Public Universe Observatory']);

    $this->get('/')
        ->assertOk()
        ->assertSee('<meta property="og:site_name" content="Public Universe Observatory">', escape: false)
        ->assertSee('"name":"Public Universe Observatory"', escape: false);

    $this->get('/about')
        ->assertOk()
        ->assertSee('subject='.rawurlencode('Public Universe Observatory — data correction'), escape: false);
});

it('falls back to the published download host when SOLAR_DOWNLOAD_URL is unset', function () {
    $snapshot = [
        'env' => getenv('SOLAR_DOWNLOAD_URL'),
        '_ENV' => $_ENV['SOLAR_DOWNLOAD_URL'] ?? null,
        '_SERVER' => $_SERVER['SOLAR_DOWNLOAD_URL'] ?? null,
    ];
    putenv('SOLAR_DOWNLOAD_URL');
    unset($_ENV['SOLAR_DOWNLOAD_URL'], $_SERVER['SOLAR_DOWNLOAD_URL']);

    try {
        /** @var array{download_url: string} $site */
        $site = require base_path('config/site.php');
        expect($site['download_url'])->toBe('https://download.publicuniverse.net/latest.json');
    } finally {
        if ($snapshot['env'] === false) {
            putenv('SOLAR_DOWNLOAD_URL');
        } else {
            putenv('SOLAR_DOWNLOAD_URL='.$snapshot['env']);
        }
        if ($snapshot['_ENV'] === null) {
            unset($_ENV['SOLAR_DOWNLOAD_URL']);
        } else {
            $_ENV['SOLAR_DOWNLOAD_URL'] = $snapshot['_ENV'];
        }
        if ($snapshot['_SERVER'] === null) {
            unset($_SERVER['SOLAR_DOWNLOAD_URL']);
        } else {
            $_SERVER['SOLAR_DOWNLOAD_URL'] = $snapshot['_SERVER'];
        }
    }
});

it('keeps the configured API and download endpoints while using the new brand', function () {
    config([
        'site.name' => 'Public Universe',
        'services.solar.base_url' => 'https://legacy-api.example.test/api/v1',
        'site.api_docs_url' => null,
        'site.download_url' => 'https://legacy-download.example.test/latest.json',
    ]);

    $this->get('/api')
        ->assertOk()
        ->assertSee('Public Universe')
        ->assertSee('https://legacy-api.example.test/api/v1', escape: false)
        ->assertSee('https://legacy-api.example.test/mcp', escape: false)
        ->assertSee('https://legacy-api.example.test/docs', escape: false)
        ->assertSee('https://legacy-download.example.test/latest.json', escape: false);
});

it('honours a separate documentation host and serves existing object permalinks', function () {
    config(['site.api_docs_url' => 'https://documentation.example.test/reference']);

    $this->get('/objects/planet-saturn')
        ->assertOk()
        ->assertSee('Saturn')
        ->assertSee('https://documentation.example.test/reference', escape: false);
});
