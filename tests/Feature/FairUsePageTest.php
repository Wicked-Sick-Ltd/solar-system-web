<?php

declare(strict_types=1);

beforeEach(function (): void {
    fakeSolar();
});

it('publishes fair use and links it from the footer', function (): void {
    $this->get('/fair-use')
        ->assertOk()
        ->assertSee('Fair use')
        ->assertSee('free for everyone', false)
        ->assertSee('X-PU-Api-Key')
        ->assertSee('60 requests per minute')
        ->assertSee('1,000 per day')
        ->assertSee('100,000')
        ->assertSee('300 requests per minute per IP')
        ->assertSee('Verified search')
        ->assertSee(route('api-access'), false)
        ->assertHeader('Cache-Control', 'max-age=0, public, s-maxage=600, stale-while-revalidate=86400');

    $this->get('/')->assertSee('href="'.route('fair-use').'"', false);
    $this->get('/sitemaps/pages.xml')->assertSee(route('fair-use'), false);
});

it('keeps robots.txt open and points operators at fair use', function (): void {
    $this->get('/robots.txt')
        ->assertOk()
        ->assertSee('# Bulk or automated access must use the API with a key. See '.url('/fair-use'), false)
        ->assertSee('Allow: /')
        ->assertSee('Sitemap:')
        ->assertDontSee('Disallow: /robots.txt')
        ->assertDontSee('Disallow: /sitemap');

    $static = file_get_contents(public_path('robots.txt'));
    expect($static)->toContain('https://publicuniverse.net/fair-use')
        ->and($static)->toContain('Allow: /')
        ->and($static)->toContain('Sitemap: https://publicuniverse.net/sitemap.xml')
        ->and($static)->not->toContain('Disallow: /sitemap')
        ->and($static)->not->toContain('Disallow: /robots.txt');
});
