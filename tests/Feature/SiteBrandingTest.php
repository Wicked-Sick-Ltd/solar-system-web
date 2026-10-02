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
