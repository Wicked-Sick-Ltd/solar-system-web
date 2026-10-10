<?php

declare(strict_types=1);

use App\Support\Format;
use App\Support\Handout;
use App\Support\KeyStage;

beforeEach(fn () => fakeSolar());

it('renders a card for every configured handout', function () {
    $response = $this->get('/educators')->assertOk();

    $handouts = Handout::all();
    expect($handouts)->toHaveCount(2);

    foreach ($handouts as $handout) {
        $response
            ->assertSee($handout->title)
            ->assertSee($handout->keyStage)
            ->assertSee($handout->description);
    }

    $response
        ->assertSee('Primary')
        ->assertSee('Secondary')
        ->assertSee(KeyStage::span(['KS1', 'KS2']))
        ->assertSee(KeyStage::span(['KS3', 'KS4', 'KS5']))
        ->assertSee(KeyStage::accessible(['KS2']))
        ->assertSee('KS2 · US Grades 2–5 · Ages 7–11')
        ->assertSee('KS1 · US Grades K–1 · Ages 5–7')
        ->assertSee('KS4 · US Grades 9–10 · Ages 14–16')
        ->assertSee('KS5 · US Grades 11–12 · Ages 16–18')
        ->assertSee('href="#primary"', false)
        ->assertSee('href="#secondary"', false)
        ->assertDontSee(':ks1')
        ->assertDontSee(':ks4');
});

it('offers each PDF as a relative download link to a file that exists', function () {
    $response = $this->get('/educators')->assertOk();

    foreach (Handout::all() as $handout) {
        $file = public_path($handout->pdf);
        expect(is_file($file))->toBeTrue("missing {$handout->pdf}")
            ->and(is_file(public_path($handout->thumbnail)))->toBeTrue("missing {$handout->thumbnail}");

        foreach ($handout->previews as $preview) {
            expect(is_file(public_path($preview)))->toBeTrue("missing {$preview}");
        }

        // Root-relative, so the same markup works on every hostname the site serves.
        $response
            ->assertSee('href="/'.$handout->pdf.'" download="'.$handout->filename().'"', escape: false)
            ->assertSee('src="/'.$handout->thumbnail.'"', escape: false)
            ->assertSee('Download PDF')
            ->assertSee(sprintf('%s, %d pages, %s', $handout->paper, $handout->pages, Format::fileSize((int) filesize($file))));
    }
});

it('serves the PDFs as static files', function () {
    foreach (Handout::all() as $handout) {
        $bytes = file_get_contents(public_path($handout->pdf));

        expect($bytes)->toStartWith('%PDF-');
    }
});

it('shows the reassurance line and a feedback mailto', function () {
    $this->get('/educators')
        ->assertOk()
        ->assertSee('Free, with no adverts.')
        ->assertSee('Pupils never need an account.')
        ->assertSee('Location is optional')
        ->assertSee('mailto:'.config('site.contact_email'), escape: false)
        ->assertSee('hello@wickedsick.com');
});

it('emits SEO metadata and LearningResource structured data', function () {
    $this->get('/educators')
        ->assertOk()
        ->assertSee('<title>Educators · '.config('site.name').'</title>', escape: false)
        ->assertSee('<link rel="canonical" href="'.url('/educators').'">', escape: false)
        ->assertSee('og:title', escape: false)
        ->assertSee('"@type":"CollectionPage"', escape: false)
        ->assertSee('"@type":"LearningResource"', escape: false)
        ->assertSee('"learningResourceType":"handout"', escape: false)
        ->assertSee('"encodingFormat":"application/pdf"', escape: false)
        ->assertSee('"isAccessibleForFree":true', escape: false)
        ->assertSee('"educationalLevel":'.json_encode(KeyStage::educationalLevels(['KS1', 'KS2']), JSON_UNESCAPED_UNICODE), escape: false)
        ->assertSee('"educationalLevel":'.json_encode(KeyStage::educationalLevels(['KS3', 'KS4', 'KS5']), JSON_UNESCAPED_UNICODE), escape: false)
        ->assertSee('"typicalAgeRange":"5-11"', escape: false)
        ->assertSee('"typicalAgeRange":"11-18"', escape: false);
});

it('is linked from the footer and listed in the sitemap', function () {
    $this->get('/about')
        ->assertOk()
        ->assertSee('href="'.route('educators').'"', escape: false)
        ->assertSee('For educators');

    $this->get('/sitemaps/pages.xml')
        ->assertOk()
        ->assertSee('<loc>'.url('/educators').'</loc>', escape: false);
});

it('is served cookie-less and edge-cacheable like the other editorial pages', function () {
    $this->get('/educators')
        ->assertOk()
        ->assertHeader('Cache-Control', 'max-age=0, public, s-maxage=600, stale-while-revalidate=86400');
});

it('connects school resources with posters, university work and private feedback', function (): void {
    $this->get(route('educators'))->assertOk()->assertSee('Exoplanet poster series')
        ->assertSee('href="#posters"', false)->assertSee(route('higher-education'), false)
        ->assertSee(route('feedback'), false)->assertSee('The first posters are being prepared');
});

it('renders configured posters with credits and downloadable local artwork', function (): void {
    config(['educators.posters' => [[
        'title' => 'Example classroom poster', 'description' => 'Test artwork description',
        'credit' => 'Example artist', 'licence' => 'Test licence',
        'pdf' => 'handouts/posters/example.pdf', 'preview' => 'handouts/posters/example.webp',
        'alt' => 'An example exoplanet diagram', 'width' => 600, 'height' => 850,
    ]]]);
    $this->get(route('educators'))->assertOk()->assertSee('Example artist')->assertSee('Test licence')
        ->assertSee('href="/handouts/posters/example.pdf" download="example.pdf"', false)
        ->assertSee('An example exoplanet diagram')->assertDontSee('The first posters are being prepared');
});
