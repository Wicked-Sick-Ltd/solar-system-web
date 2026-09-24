<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(fn () => fakeSolar());

it('sends baseline security headers on every page', function () {
    $this->get('/')
        ->assertOk()
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
        ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
        ->assertHeader('Permissions-Policy', 'geolocation=(), camera=(), microphone=(), interest-cohort=()');
});

it('makes non-interactive public pages edge-cacheable and cookie-less', function (string $uri) {
    $response = $this->get($uri)->assertOk();

    expect($response->headers->get('Cache-Control'))
        ->toContain('public')
        ->toContain('s-maxage=600')
        ->toContain('stale-while-revalidate');

    // No session cookie, so a shared cache can store one copy for everyone.
    expect($response->headers->getCookies())->toBe([]);
})->with([
    'home' => '/',
    'planets' => '/planets',
    'about' => '/about',
    'api' => '/api',
    'dwarf-planets' => '/dwarf-planets',
]);

it('does not edge-cache interactive pages (they need the session)', function (string $uri) {
    $cacheControl = (string) $this->get($uri)->assertOk()->headers->get('Cache-Control');

    expect($cacheControl)->not->toContain('public');
})->with([
    'objects index' => '/objects',
    'search' => '/search?q=ceres',
    'asteroids' => '/asteroids',
    'object detail' => '/objects/planet-saturn',
    'login' => '/login',
]);

it('does not edge-cache even cacheable routes when a user is signed in', function () {
    $user = User::factory()->create();
    $cacheControl = (string) $this->actingAs($user)->get('/')->assertOk()->headers->get('Cache-Control');

    expect($cacheControl)->not->toContain('public');
});

it('makes the sitemap and robots.txt publicly cacheable', function () {
    expect($this->get('/sitemap.xml')->assertOk()->headers->get('Cache-Control'))
        ->toContain('public');

    expect($this->get('/robots.txt')->assertOk()->headers->get('Cache-Control'))
        ->toContain('public');
});
