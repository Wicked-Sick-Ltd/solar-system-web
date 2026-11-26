<?php

declare(strict_types=1);

use App\Livewire\NewsletterSignup;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Livewire;
use Livewire\Mechanisms\HandleRequests\HandleRequests;

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

it('gives an existing subscriber the same "Check your inbox" reply and does not re-subscribe them', function () {
    fakeMailchimp(200, ['status' => 'subscribed']);

    Livewire::test(NewsletterSignup::class)
        ->set('email', 'proto@example.com')
        ->call('subscribe')
        ->assertSet('state', 'pending')
        ->assertSee('Check your inbox')
        ->assertDontSee('already on the list');

    // One add-or-update call that never sets status, so Mailchimp leaves the
    // confirmed member alone and sends no new confirmation email.
    Http::assertSentCount(1);
    Http::assertSent(fn ($request) => $request->method() === 'PUT'
        && ($request['status_if_new'] ?? null) === 'pending'
        && ! isset($request['status']));
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

it('resubscribes an unsubscribed member by requesting pending status explicitly', function () {
    Http::fake([
        'https://us21.api.mailchimp.com/3.0/lists/aud42/members/*' => Http::sequence()
            ->push(['status' => 'unsubscribed'], 200)
            ->push(['status' => 'pending'], 200),
        '*' => Http::response(['results' => []]),
    ]);

    Livewire::test(NewsletterSignup::class)
        ->set('email', 'proto@example.com')
        ->call('subscribe')
        ->assertSet('state', 'pending');

    Http::assertSentCount(2);
    Http::assertSent(fn ($r) => $r->method() === 'PUT' && ($r['status'] ?? null) === 'pending');
});

it('dispatches a GA4 newsletter_signup browser event only for a new double opt-in sign-up', function () {
    fakeMailchimp();

    Livewire::test(NewsletterSignup::class)
        ->set('email', 'ga4@example.com')
        ->call('subscribe')
        ->assertSet('state', 'pending')
        ->assertDispatched('pu-analytics', function (string $event, array $params): bool {
            return $params === ['name' => 'newsletter_signup', 'source' => 'footer', 'method' => 'mailchimp_double_opt_in'];
        });
});

it('sends no newsletter_signup when there is no "Check your inbox" reply', function (int $status, array $body, string $email, string $honeypot, ?string $state) {
    fakeMailchimp($status, $body);

    $component = Livewire::test(NewsletterSignup::class)
        ->set('email', $email)
        ->set('website', $honeypot)
        ->call('subscribe');

    if ($state === null) {
        $component->assertHasErrors(['email']);
    } else {
        $component->assertSet('state', $state);
    }
    $component->assertNotDispatched('pu-analytics');
})->with([
    'validation error' => [200, ['status' => 'pending'], 'not-an-email', '', null],
    'mailchimp failure' => [400, ['title' => 'Invalid Resource', 'detail' => 'looks fake or invalid'], 'down@example.com', '', 'error'],
]);

it('gives a new sign-up, a repeat pending submission, an existing subscriber and the honeypot the same response and event', function (array $body, string $honeypot) {
    fakeMailchimp(200, $body);

    Livewire::test(NewsletterSignup::class)
        ->set('email', 'same@example.com')
        ->set('website', $honeypot)
        ->call('subscribe')
        ->assertSet('state', 'pending')
        ->assertSee('Check your inbox')
        ->assertDispatched('pu-analytics', function (string $event, array $params): bool {
            return $params === ['name' => 'newsletter_signup', 'source' => 'footer', 'method' => 'mailchimp_double_opt_in'];
        });
})->with([
    'new or repeat pending (Mailchimp answers pending for both)' => [['status' => 'pending'], ''],
    'already subscribed' => [['status' => 'subscribed'], ''],
    'honeypot' => [['status' => 'pending'], 'http://spam.example'],
]);

/**
 * Submit the footer form through Livewire's real update endpoint, from one
 * shared initial snapshot, and return what a visitor (or a script probing
 * the list) gets back.
 *
 * @param  string  $mailchimpStatus  the member status Mailchimp answers with
 * @return array{status: int, type: string|null, body: string, json: array<string, mixed>, mailchimp_calls: int}
 */
function submitNewsletterOverHttp(string $snapshot, string $mailchimpStatus, string $email, string $honeypot): array
{
    RateLimiter::clear('newsletter:127.0.0.1');
    // A fresh client factory per case: Http::fake() stubs stack, and the
    // first matching one would otherwise answer every later case.
    Http::swap(new HttpFactory(app('events')));
    fakeMailchimp(200, ['status' => $mailchimpStatus]);

    $response = test()->withHeaders(['X-Livewire' => '1'])->postJson(
        app(HandleRequests::class)->getUpdateUri(),
        ['components' => [[
            'snapshot' => $snapshot,
            'updates' => ['email' => $email, 'website' => $honeypot],
            'calls' => [['path' => '', 'method' => 'subscribe', 'params' => []]],
        ]]],
    );

    $mailchimpCalls = collect(Http::recorded())
        ->filter(fn (array $pair) => str_contains($pair[0]->url(), 'api.mailchimp.com'))
        ->count();

    return [
        'status' => $response->getStatusCode(),
        'type' => $response->headers->get('Content-Type'),
        'body' => (string) $response->getContent(),
        'json' => (array) $response->json(),
        'mailchimp_calls' => $mailchimpCalls,
    ];
}

it('returns byte-identical HTTP responses for new, pending, already-subscribed and honeypot submissions', function () {
    preg_match('/wire:snapshot="([^"]+)"/', Livewire::mount(NewsletterSignup::class), $m);
    $snapshot = html_entity_decode($m[1], ENT_QUOTES | ENT_HTML5);
    expect($snapshot)->not->toBe('');

    $email = 'probe@example.com';
    $cases = [
        // Mailchimp creates a new member as pending.
        'new' => submitNewsletterOverHttp($snapshot, 'pending', $email, ''),
        // An unconfirmed member stays pending (status_if_new is ignored).
        'pending' => submitNewsletterOverHttp($snapshot, 'pending', $email, ''),
        // A confirmed member stays subscribed and gets no email.
        'already subscribed' => submitNewsletterOverHttp($snapshot, 'subscribed', $email, ''),
        'honeypot' => submitNewsletterOverHttp($snapshot, 'pending', $email, 'http://spam.example'),
    ];

    $new = $cases['new'];
    expect($new['status'])->toBe(200)
        ->and($new['body'])->toContain('Check your inbox')
        ->and($new['body'])->toContain('newsletter_signup')
        ->and($new['body'])->not->toContain('already on the list');

    // Same status, content type and body, byte for byte, which also means the
    // same JSON shape and the same newsletter_signup dispatch.
    foreach (['pending', 'already subscribed'] as $name) {
        expect($cases[$name]['status'])->toBe($new['status'], $name)
            ->and($cases[$name]['type'])->toBe($new['type'], $name)
            ->and($cases[$name]['body'])->toBe($new['body'], $name);
    }

    // Same timing class: one Mailchimp round trip whether or not the address
    // is already on the list.
    expect(array_map(fn (array $case) => $case['mailchimp_calls'], $cases))
        ->toBe(['new' => 1, 'pending' => 1, 'already subscribed' => 1, 'honeypot' => 0]);

    // The honeypot request differs only in the hidden field it sent, which
    // the snapshot echoes back (and so its checksum). Everything else matches.
    $strip = function (array $json): array {
        $component = $json['components'][0];
        $snap = json_decode($component['snapshot'], true);
        unset($snap['data']['website'], $snap['checksum']);
        $component['snapshot'] = $snap;

        return ['components' => [$component]] + $json;
    };
    $honeypot = $cases['honeypot'];
    expect($honeypot['status'])->toBe($new['status'])
        ->and($honeypot['type'])->toBe($new['type'])
        ->and($strip($honeypot['json']))->toBe($strip($new['json']));
});
