<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    fakeSolar();
    Storage::fake((string) config('og.disk'));
});

it('resolves every object url published in the sitemap', function () {
    $xml = $this->get('/sitemap.xml')->assertOk()->getContent();

    preg_match_all('#<loc>([^<]+)</loc>#', $xml, $matches);
    $paths = [];
    foreach ($matches[1] as $loc) {
        $path = parse_url(html_entity_decode($loc), PHP_URL_PATH) ?? '';
        if (str_starts_with($path, '/objects/')) {
            $paths[] = $path;
        }
    }

    $paths = array_values(array_unique($paths));
    $slashMoons = array_values(array_filter(
        $paths,
        fn (string $path) => str_contains($path, '/objects/moon-s/'),
    ));

    expect($paths)->not->toBeEmpty()
        ->and($slashMoons)->not->toBeEmpty()
        ->and($paths)->toContain('/objects/moon-s-2019-s-22')
        ->and($slashMoons)->toContain('/objects/moon-s/2019-s-1')
        ->and($slashMoons)->toContain('/objects/moon-s/2003-j-12')
        ->and($slashMoons)->toContain('/objects/moon-s/2020-s-11')
        ->and($slashMoons)->toContain('/objects/moon-s/2020-s-17');

    foreach ($paths as $path) {
        $response = $this->get($path)->assertOk();
        $id = rawurldecode(substr($path, strlen('/objects/')));
        $name = provisionalMoonNames()[$id] ?? null;
        if ($name !== null) {
            $response->assertSee($name, escape: false);
            $response->assertSee($path, escape: false);
        }
    }

    $this->get('/og/objects/moon-s/2019-s-1.png')->assertOk();
});
