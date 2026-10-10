<?php

declare(strict_types=1);

use App\Services\Og\CatalogueFigures;
use App\Services\Og\OgImageRenderer;
use App\Support\ShareImage;
use Carbon\CarbonImmutable;
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

it('renders the site card with live catalogue counts and caches it per set of counts', function () {
    $response = $this->get(ShareImage::defaultUrl())
        ->assertOk()
        ->assertHeader('Content-Type', 'image/png');

    // Counts change nightly under the same URL, so this card is not immutable.
    expect($response->headers->get('Cache-Control'))->toContain('max-age=86400')->not->toContain('immutable')
        ->and(getimagesizefromstring($response->getContent())[0])->toBe(1200)
        ->and(getimagesizefromstring($response->getContent())[1])->toBe(630)
        ->and($response->getContent())->not->toBe(file_get_contents(public_path(ShareImage::DEFAULT_PATH)));

    $files = Storage::disk(config('og.disk'))->allFiles('og/'.ShareImage::version().'/site');
    expect($files)->toHaveCount(1);

    $this->get(ShareImage::defaultUrl())->assertOk();
    expect(Storage::disk(config('og.disk'))->allFiles('og/'.ShareImage::version().'/site'))->toBe($files);
});

it('reads exoplanet, moon and object counts for the site card from the API', function () {
    expect(app(CatalogueFigures::class)->all())->toBe([
        ['value' => '15,546', 'label' => 'Solar-system objects'],
        ['value' => '273', 'label' => 'Moons'],
        ['value' => '1', 'label' => 'Exoplanets'],
    ]);
});

it('serves the committed card when the catalogue counts are unavailable', function () {
    fakeSolarDown();
    Storage::fake(config('og.disk'));

    $response = $this->get(ShareImage::defaultUrl())->assertOk()->assertHeader('Content-Type', 'image/png');

    expect($response->getContent())->toBe(file_get_contents(public_path(ShareImage::DEFAULT_PATH)))
        ->and(Storage::disk(config('og.disk'))->allFiles())->toBe([]);
});

it('renders, caches and serves an object-of-the-day card per date', function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-25 10:00:00', 'UTC'));
    $url = ShareImage::todayUrl(CarbonImmutable::parse('2026-10-23'));

    $response = $this->get($url)->assertOk()->assertHeader('Content-Type', 'image/png');

    expect($response->headers->get('Cache-Control'))->toContain('immutable')
        ->and(getimagesizefromstring($response->getContent())[0])->toBe(1200);
    Storage::disk(config('og.disk'))->assertExists('og/'.ShareImage::version().'/today/2026-10-23.png');
    expect($this->get($url)->getContent())->toBe($response->getContent());
});

it('falls back to the committed card for an object-of-the-day date that is not a permalink', function (string $date) {
    $this->travelTo(CarbonImmutable::parse('2026-10-25 10:00:00', 'UTC'));

    $response = $this->get('/og/today/'.$date.'.png')->assertOk();

    expect($response->getContent())->toBe(file_get_contents(public_path(ShareImage::DEFAULT_PATH)))
        ->and(Storage::disk(config('og.disk'))->allFiles())->toBe([]);
})->with(['2026-10-26', '2026-02-30']);

it('renders every card type as a 1200×630 PNG', function () {
    $renderer = app(OgImageRenderer::class);
    $figures = [['value' => '1,576,285', 'label' => 'Solar-system objects'], ['value' => '6,445', 'label' => 'Exoplanets']];
    $cards = [
        $renderer->render('Saturn', 'Planet', '#EAD6A0', giant: true),
        $renderer->renderSite('Public Universe', 'Astronomy for everyone', $figures, 'publicuniverse.net'),
        $renderer->renderSite('Public Universe', 'Astronomy for everyone', []),
        $renderer->renderToday('Object of the day · Fri 23 Oct 2026', 'An exceptionally long provisional designation', 'Moon',
            str_repeat('A fact long enough to need wrapping and truncating. ', 8), [['value' => '504 km', 'label' => 'Diameter']], null, 'publicuniverse.net/today/2026-10-23', rings: true),
    ];

    foreach ($cards as $png) {
        $size = getimagesizefromstring($png);
        expect($size[0])->toBe(1200)->and($size[1])->toBe(630)->and($size['mime'])->toBe('image/png');
    }
});

it('renders the same site card bytes for the same inputs', function () {
    $renderer = app(OgImageRenderer::class);
    $at = CarbonImmutable::parse('2026-10-12', 'UTC');

    expect($renderer->renderSite('Public Universe', 'Astronomy for everyone', [], null, $at))
        ->toBe($renderer->renderSite('Public Universe', 'Astronomy for everyone', [], null, $at));
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
