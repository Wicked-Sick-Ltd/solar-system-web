<?php

declare(strict_types=1);

use App\Models\ApiAccessRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    fakeSolar();
    config([
        'api_access.slack_webhook_url' => null,
        'api_access.asana_access_token' => null,
        'api_access.asana_project_id' => null,
    ]);
    Http::fake();
});

function accessPayload(array $overrides = []): array
{
    return array_replace([
        'organisation' => 'Institute of Astronomy',
        'contact_name' => 'Ada Lovelace',
        'email' => 'ada@cam.ac.uk',
        'use_case' => 'Nightly classroom catalogue extracts for a taught module.',
        'expected_volume' => '1000-10000',
    ], $overrides);
}

it('shows the request form without sending notifications', function (): void {
    $this->get(route('api-access'))
        ->assertOk()
        ->assertSee('Academic or work email')
        ->assertSee('.ac.uk')
        ->assertSee('name="_token"', false)
        ->assertSee('name="website"', false)
        ->assertSee('Egress IP ranges (CIDR)')
        ->assertSee('300 requests per minute per IP')
        ->assertHeader('Cache-Control', 'no-store, private');

    Http::assertNothingSent();
});

it('stores a request and flags academic domains', function (string $email, bool $eligible): void {
    $this->post(route('api-access.store'), accessPayload(['email' => $email]))
        ->assertRedirect(route('api-access'))
        ->assertSessionHas('api_access_sent', true)
        ->assertSessionHas('api_access_institution', $eligible);

    $row = ApiAccessRequest::query()->first();
    expect($row)->not->toBeNull()
        ->and($row->email)->toBe($email)
        ->and($row->institution_eligible)->toBe($eligible)
        ->and($row->organisation)->toBe('Institute of Astronomy');
    Http::assertNothingSent();
})->with([
    ['ada@cam.ac.uk', true],
    ['ada@mit.edu', true],
    ['ada@sydney.edu.au', true],
    ['ada@lab.example', false],
]);

it('notifies slack and asana only when those settings are present', function (): void {
    config([
        'api_access.slack_webhook_url' => 'https://hooks.slack.com/services/T00/B00/secret',
        'api_access.asana_access_token' => 'asana-token',
        'api_access.asana_project_id' => '1220017034322863',
    ]);

    $this->post(route('api-access.store'), accessPayload([
        'egress_cidrs' => "203.0.113.5/24\n2001:db8::1/48",
    ]))->assertSessionHas('api_access_sent', true);

    expect(ApiAccessRequest::query()->first()?->egress_cidrs)->toBe("203.0.113.0/24\n2001:db8::/48");

    Http::assertSent(function ($request): bool {
        return $request->url() === 'https://hooks.slack.com/services/T00/B00/secret'
            && str_contains((string) $request['text'], 'ada@cam.ac.uk')
            && str_contains((string) $request['text'], 'free institution tier')
            && str_contains((string) $request['text'], '203.0.113.0/24, 2001:db8::/48');
    });
    Http::assertSent(function ($request): bool {
        return $request->url() === 'https://app.asana.com/api/1.0/tasks'
            && $request->hasHeader('Authorization', 'Bearer asana-token')
            && ($request['data']['projects'][0] ?? null) === '1220017034322863'
            && str_contains((string) ($request['data']['notes'] ?? ''), 'Institute of Astronomy')
            && str_contains((string) ($request['data']['notes'] ?? ''), '203.0.113.0/24, 2001:db8::/48');
    });
});

it('stores a request with no egress ranges', function (): void {
    $this->post(route('api-access.store'), accessPayload())->assertSessionHas('api_access_sent', true);
    expect(ApiAccessRequest::query()->first()?->egress_cidrs)->toBeNull();
});

it('stores the request when a webhook fails and when only one asana setting is set', function (): void {
    config([
        'api_access.slack_webhook_url' => 'https://hooks.slack.com/services/T00/B00/secret',
        'api_access.asana_access_token' => 'asana-token',
        'api_access.asana_project_id' => null,
    ]);
    Http::fake([
        'https://hooks.slack.com/*' => Http::response('nope', 500),
    ]);

    $this->post(route('api-access.store'), accessPayload())
        ->assertSessionHas('api_access_sent', true);
    expect(ApiAccessRequest::query()->count())->toBe(1);
    Http::assertNotSent(fn ($request): bool => str_contains($request->url(), 'asana.com'));
});

it('rejects invalid and spam submissions without storing them', function (array $input, string $error): void {
    $this->post(route('api-access.store'), accessPayload($input))
        ->assertRedirect(route('api-access'))
        ->assertSessionHasErrors($error)
        ->assertSessionMissing('api_access_sent');
    expect(ApiAccessRequest::query()->count())->toBe(0);
    Http::assertNothingSent();
})->with([
    [['organisation' => 'A'], 'organisation'],
    [['organisation' => "Lab\r\nBcc: evil"], 'organisation'],
    [['contact_name' => ''], 'contact_name'],
    [['email' => 'not-an-email'], 'email'],
    [['email' => "a@cam.ac.uk\nBcc:x@y.z"], 'email'],
    [['use_case' => 'too short'], 'use_case'],
    [['use_case' => str_repeat('x', 5001)], 'use_case'],
    [['expected_volume' => 'unlimited'], 'expected_volume'],
    [['egress_cidrs' => '203.0.113.5'], 'egress_cidrs'],
    [['egress_cidrs' => '203.0.113.0/33'], 'egress_cidrs'],
    [['egress_cidrs' => '0.0.0.0/0'], 'egress_cidrs'],
    [['egress_cidrs' => "203.0.113.0/24\nnot-a-cidr"], 'egress_cidrs'],
    [['website' => 'https://spam.example'], 'website'],
]);

it('rate limits repeated submissions', function (): void {
    for ($i = 0; $i < 5; $i++) {
        $this->post(route('api-access.store'), accessPayload(['organisation' => 'Lab '.$i]))
            ->assertRedirect();
    }
    $this->post(route('api-access.store'), accessPayload())
        ->assertStatus(429)
        ->assertHeader('Cache-Control', 'no-store, private');
    expect(ApiAccessRequest::query()->count())->toBe(5);
});
