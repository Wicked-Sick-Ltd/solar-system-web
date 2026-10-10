<?php

declare(strict_types=1);

use App\Support\ShareImage;
use Carbon\CarbonImmutable;

beforeEach(function () {
    fakeSolar();
    // 23 October 2026 features Saturn; "today" is two days later.
    $this->travelTo(CarbonImmutable::parse('2026-10-25 10:00:00', 'UTC'));
});

function todayDocument(string $html): DOMXPath
{
    $document = new DOMDocument;
    $document->loadHTML($html, LIBXML_NOERROR | LIBXML_NOWARNING);

    return new DOMXPath($document);
}

it('redirects /today to the current UTC day’s dated permalink without caching the redirect', function () {
    $response = $this->get('/today')->assertRedirect('/today/2026-10-25');

    expect($response->headers->get('Cache-Control'))->toContain('no-cache');
});

it('shows the featured object, its fun fact and key stats on the dated permalink', function () {
    $this->get('/today/2026-10-23')
        ->assertOk()
        ->assertSee('<h1 id="today-heading" class="mt-2 font-serif text-4xl font-medium sm:text-5xl">Saturn</h1>', escape: false)
        ->assertSee('<time datetime="2026-10-23">Friday 23 October 2026</time>', escape: false)
        ->assertSee('Saturn is less dense than water — 0.69 g/cm³ on average.')
        ->assertSee('116,464 km')
        ->assertSee('9.54 AU')
        ->assertSee('29.46 years')
        ->assertSee(route('objects.show', 'planet-saturn'), escape: false);
});

it('gives the permalink its own canonical URL and per-day share card', function () {
    $html = $this->get('/today/2026-10-23')->assertOk()->getContent();
    $document = todayDocument($html);
    $card = ShareImage::todayUrl(CarbonImmutable::parse('2026-10-23'));

    expect($document->evaluate('string(//link[@rel="canonical"]/@href)'))->toBe(url('/today/2026-10-23'))
        ->and($document->evaluate('string(//meta[@property="og:url"]/@content)'))->toBe(url('/today/2026-10-23'))
        ->and($document->evaluate('string(//meta[@property="og:image"]/@content)'))->toBe($card)
        ->and($document->evaluate('string(//meta[@name="twitter:image"]/@content)'))->toBe($card)
        ->and($document->evaluate('string(//meta[@name="twitter:card"]/@content)'))->toBe('summary_large_image')
        ->and($document->evaluate('string(//meta[@property="og:image:alt"]/@content)'))->toContain('Saturn')
        ->and($document->evaluate('string(//meta[@property="og:title"]/@content)'))->toBe('Object of the day: Saturn')
        ->and($document->evaluate('string(//meta[@name="description"]/@content)'))->toStartWith('Saturn is less dense than water');
});

it('offers accessible copy, LinkedIn, X and native share controls for the permalink', function () {
    $document = todayDocument($this->get('/today/2026-10-23')->assertOk()->getContent());
    $group = '//div[@role="group"][@aria-label="Share Saturn"]';
    $permalink = url('/today/2026-10-23');

    expect($document->evaluate("count($group)"))->toBe(1.0)
        ->and($document->evaluate("count($group//button[@type='button'][normalize-space()='Copy link'])"))->toBe(1.0)
        ->and($document->evaluate("count($group//button[@type='button'][@x-show='canShare'][normalize-space()='Share…'])"))->toBe(1.0)
        ->and($document->evaluate("string($group//a[contains(@href, 'linkedin.com/sharing/share-offsite')]/@href)"))
        ->toBe('https://www.linkedin.com/sharing/share-offsite/?url='.rawurlencode($permalink))
        ->and($document->evaluate("string($group//a[contains(@href, 'x.com/intent')]/@href)"))->toContain('url='.rawurlencode($permalink))
        ->and($document->evaluate("count($group//a[@target='_blank'][@rel='noopener noreferrer'][span[@class='sr-only'][contains(., 'opens in a new tab')]])"))->toBe(2.0)
        ->and($document->evaluate("string($group//input[@readonly]/@value)"))->toBe($permalink)
        ->and($document->evaluate("string(//label[@for=$group//input/@id])"))->toBe('Permalink')
        ->and($document->evaluate("count($group//*[@role='status'][@aria-live='polite'])"))->toBe(1.0);
});

it('links to neighbouring days, never beyond today', function () {
    $document = todayDocument($this->get('/today/2026-10-25')->assertOk()->getContent());
    $nav = '//nav[@aria-label="Object of the day by date"]';

    expect($document->evaluate("string($nav/a[@rel='prev']/@href)"))->toBe(url('/today/2026-10-24'))
        ->and($document->evaluate("count($nav/a[@rel='next'])"))->toBe(0.0);

    $document = todayDocument($this->get('/today/2026-10-23')->assertOk()->getContent());
    expect($document->evaluate("string($nav/a[@rel='next']/@href)"))->toBe(url('/today/2026-10-24'));
});

it('404s for dates that are not valid permalinks', function (string $date) {
    $this->get('/today/'.$date)->assertNotFound();
})->with(['2026-10-26', '2026-02-30', '2025-12-31', 'yesterday']);

it('degrades to a status panel when the catalogue is down', function () {
    fakeSolarDown();

    $this->get('/today/2026-10-23')
        ->assertOk()
        ->assertSee('<meta name="robots" content="noindex, follow">', escape: false)
        ->assertSee('temporarily unavailable');
});

it('gives the homepage featured object a dated permalink and share controls', function () {
    $document = todayDocument($this->get('/')->assertOk()->getContent());
    $permalink = url('/today/2026-10-25');

    expect($document->evaluate("count(//section[@aria-labelledby='featured-heading']//a[@href='$permalink'])"))->toBe(1.0)
        ->and($document->evaluate("count(//section[@aria-labelledby='featured-heading']//div[@role='group'][starts-with(@aria-label, 'Share today')])"))->toBe(1.0)
        ->and($document->evaluate("string(//section[@aria-labelledby='featured-heading']//a[contains(@href, 'linkedin.com')]/@href)"))->toContain(rawurlencode($permalink));
});

it('features the same object on the homepage and the permalink for that day', function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-23 23:59:00', 'UTC'));

    $this->get('/')->assertOk()->assertSee('Saturn');
    $this->get('/today')->assertRedirect('/today/2026-10-23');
});
