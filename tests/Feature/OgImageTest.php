<?php

declare(strict_types=1);

use App\Support\ShareImage;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    fakeSolar();
    Storage::fake(config('og.disk'));
});

it('renders, caches and serves a per-object share card', function () {
    $path = 'og/'.ShareImage::version().'/'.sha1('planet-saturn').'.png';

    $response = $this->get('/og/objects/planet-saturn.png')
        ->assertOk()
        ->assertHeader('Content-Type', 'image/png');

    expect($response->headers->get('Cache-Control'))->toContain('immutable');
    Storage::disk(config('og.disk'))->assertExists($path);

    // The bytes are a real PNG.
    expect(substr($response->getContent(), 0, 4))->toBe("\x89PNG");
});

it('serves the cached card on subsequent requests', function () {
    $this->get('/og/objects/planet-saturn.png')->assertOk();
    $this->get('/og/objects/planet-saturn.png')->assertOk()->assertHeader('Content-Type', 'image/png');
});

it('falls back to the static card for an unknown object', function () {
    $this->get('/og/objects/missing-object.png')
        ->assertOk()
        ->assertHeader('Content-Type', 'image/png');
});

it('points the object page og:image at the per-object card', function () {
    $this->get('/objects/planet-saturn')
        ->assertOk()
        ->assertSee('/og/objects/planet-saturn.png?v='.ShareImage::version(), escape: false);
});

it('changes public image URLs and cached bytes when the brand changes', function () {
    $firstUrl = ShareImage::objectUrl('planet-saturn');
    $firstVersion = ShareImage::version();
    $first = $this->get($firstUrl)->assertOk()->getContent();

    config(['site.name' => 'New Observatory']);
    $secondUrl = ShareImage::objectUrl('planet-saturn');
    $secondVersion = ShareImage::version();
    $second = $this->get($secondUrl)->assertOk()->getContent();

    expect($secondUrl)->not->toBe($firstUrl)
        ->and($secondVersion)->not->toBe($firstVersion)
        ->and($second)->not->toBe($first);
    Storage::disk(config('og.disk'))->assertExists('og/'.$firstVersion.'/'.sha1('planet-saturn').'.png');
    Storage::disk(config('og.disk'))->assertExists('og/'.$secondVersion.'/'.sha1('planet-saturn').'.png');
});

it('versions both default and object URLs for tagline and design changes', function (string $key) {
    $objectUrl = ShareImage::objectUrl('planet-saturn');
    $defaultUrl = ShareImage::defaultUrl();
    config([$key => 'Changed / ../../ value']);
    expect(ShareImage::objectUrl('planet-saturn'))->not->toBe($objectUrl)
        ->and(ShareImage::defaultUrl())->not->toBe($defaultUrl)
        ->and(ShareImage::version())->toMatch('/^[a-f0-9]{20}$/D');
})->with(['site.tagline', 'og.version']);

it('does not use a caller supplied version in the disk path', function () {
    $this->get('/og/objects/planet-saturn.png?v=../../untrusted')->assertOk();
    expect(Storage::disk(config('og.disk'))->allFiles())->toBe([
        'og/'.ShareImage::version().'/'.sha1('planet-saturn').'.png',
    ]);
});

it('serves the new committed Public Universe card as fallback', function () {
    $response = $this->get('/og/objects/missing-object.png')->assertOk();
    expect($response->getContent())->toBe(file_get_contents(public_path(ShareImage::DEFAULT_PATH)));
    $image = getimagesizefromstring($response->getContent());
    expect($image[0])->toBe(1200)->and($image[1])->toBe(630);
});

it('generates a default card for configured branding in an isolated public directory', function () {
    $directory = sys_get_temp_dir().'/public-universe-og-'.bin2hex(random_bytes(8));
    $this->app->usePublicPath($directory);
    config(['site.name' => 'Classroom Observatory', 'site.tagline' => 'Explore together']);
    try {
        $this->artisan('og:generate-default')->assertExitCode(0);
        $image = getimagesize($directory.'/'.ShareImage::DEFAULT_PATH);
        expect($image[0])->toBe(1200)->and($image[1])->toBe(630)
            ->and($image['mime'])->toBe('image/png');
    } finally {
        File::deleteDirectory($directory);
    }
});
