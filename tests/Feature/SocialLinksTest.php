<?php

declare(strict_types=1);

use App\Support\Social;

it('renders the X follow link in the footer with rel me and an accessible label', function () {
    $html = $this->get(route('about'))->assertOk()->getContent();

    expect($html)
        ->toContain('href="https://x.com/PublicUnivNet" rel="me noopener"')
        ->toContain('aria-label="Follow Public Universe on X (opens in a new tab)"')
        ->toContain('Follow on X')
        ->toContain('Follow Public Universe');
});

it('emits Organization JSON-LD with sameAs on the about page', function () {
    $html = $this->get(route('about'))->getContent();

    expect($html)->toContain('"@type":"Organization"')
        ->toContain('"sameAs":["https://x.com/PublicUnivNet"]');
});

it('only renders networks that are configured', function () {
    config(['site.social.bluesky' => 'https://bsky.app/profile/publicuniverse.net', 'site.social.youtube' => '']);

    expect(array_keys(Social::profiles()))->toBe(['x', 'bluesky']);

    $html = $this->get(route('about'))->getContent();
    expect($html)->toContain('Follow on Bluesky')->not->toContain('Follow on YouTube')
        ->toContain('"sameAs":["https://x.com/PublicUnivNet","https://bsky.app/profile/publicuniverse.net"]');
});

it('hides the follow block when no profiles are set', function () {
    config(['site.social' => ['x' => null]]);

    expect($this->get(route('about'))->getContent())->not->toContain('data-testid="social-links"');
});
