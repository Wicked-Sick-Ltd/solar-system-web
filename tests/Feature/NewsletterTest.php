<?php

declare(strict_types=1);

use App\Livewire\NewsletterSignup;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Livewire;

beforeEach(function () {
    RateLimiter::clear('newsletter:127.0.0.1');
    config([
        'services.mailchimp.api_key' => 'abc123-us21',
        'services.mailchimp.audience_id' => 'aud42',
    ]);
});

/** Stub Mailchimp only: fakeSolar()'s catch-all would otherwise answer first. */
function fakeMailchimp(int $status = 200, array $body = ['status' => 'pending']): void
{
    Http::fake([
        'https://us21.api.mailchimp.com/3.0/lists/aud42/members/*' => Http::response($body, $status),
        '*' => Http::response(['results' => []]),
    ]);
}

it('hides the signup form entirely when Mailchimp is not configured', function () {
    fakeSolar();
    config(['services.mailchimp.api_key' => null]);

    $this->get('/')->assertOk()->assertDontSee('newsletter-form', escape: false);
});

it('renders the signup form in the footer when configured', function () {
    fakeSolar();
    $this->get('/')->assertOk()->assertSee('newsletter-form', escape: false);
});

it('does not edge-cache pages when the signup form is present', function (string $uri) {
    fakeSolar();

    $cacheControl = (string) $this->get($uri)->assertOk()->headers->get('Cache-Control');

    expect($cacheControl)->not->toContain('public');
})->with([
    'home' => '/',
    'planets' => '/planets',
    'about' => '/about',
    'api' => '/api',
    'dwarf-planets' => '/dwarf-planets',
]);

it('subscribes a valid address with double opt-in and confirms', function () {
    fakeMailchimp();

    Livewire::test(NewsletterSignup::class)
        ->set('email', 'proto@example.com')
        ->call('subscribe')
        ->assertSet('state', 'pending')
        ->assertSee('Check your inbox');

    Http::assertSent(function ($request) {
        return $request->method() === 'PUT'
            && str_ends_with($request->url(), '/lists/aud42/members/'.md5('proto@example.com'))
            && $request['email_address'] === 'proto@example.com'
            && $request['status_if_new'] === 'pending';
    });
});

it('tells an existing subscriber they are already on the list', function () {
    fakeMailchimp(200, ['status' => 'subscribed']);

    Livewire::test(NewsletterSignup::class)
        ->set('email', 'proto@example.com')
        ->call('subscribe')
        ->assertSet('state', 'subscribed')
        ->assertSee('already');
});

it('restarts double opt-in for a previously listed address', function (string $existing) {
    Http::fake([
        'https://us21.api.mailchimp.com/3.0/lists/aud42/members/*' => Http::sequence()
            ->push(['status' => $existing])
            ->push(['status' => 'pending']),
        '*' => Http::response(['results' => []]),
    ]);

    Livewire::test(NewsletterSignup::class)
        ->set('email', 'proto@example.com')
        ->call('subscribe')
        ->assertSet('state', 'pending')
        ->assertSee('Check your inbox');

    Http::assertSent(fn ($request) => $request->method() === 'PUT'
        && ($request['status_if_new'] ?? null) === 'pending'
        && ! isset($request['status']));

    Http::assertSent(fn ($request) => $request->method() === 'PUT'
        && ($request['status'] ?? null) === 'pending');
})->with(['unsubscribed', 'cleaned', 'archived']);

it('rejects an invalid address without calling Mailchimp', function () {
    fakeMailchimp();

    Livewire::test(NewsletterSignup::class)
        ->set('email', 'not-an-email')
        ->call('subscribe')
        ->assertHasErrors(['email']);

    Http::assertNothingSent();
});

it('silently drops submissions that fill the honeypot', function () {
    fakeMailchimp();

    Livewire::test(NewsletterSignup::class)
        ->set('email', 'bot@example.com')
        ->set('website', 'http://spam.example')
        ->call('subscribe')
        ->assertSet('state', 'pending');

    Http::assertNothingSent();
});

it('shows a friendly error when Mailchimp rejects the address', function () {
    fakeMailchimp(400, ['title' => 'Invalid Resource', 'detail' => 'looks fake or invalid']);

    Livewire::test(NewsletterSignup::class)
        ->set('email', 'proto@example.com')
        ->call('subscribe')
        ->assertSet('state', 'error')
        ->assertSee('could not');
});

it('rate-limits repeated attempts from one address', function () {
    fakeMailchimp();

    $c = Livewire::test(NewsletterSignup::class);
    foreach (range(1, 5) as $i) {
        $c->set('email', "p{$i}@example.com")->call('subscribe');
    }
    $c->set('email', 'p6@example.com')->call('subscribe')->assertHasErrors(['email']);
});
