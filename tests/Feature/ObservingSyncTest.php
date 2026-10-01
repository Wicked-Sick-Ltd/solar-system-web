<?php

use App\Http\Middleware\PrivateObservingSync;
use App\Models\PrivateObservingWorkspace;
use App\Models\User;
use App\Services\Observing\ObservingAccountScope;
use App\Services\Observing\SyncPayload;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

uses(RefreshDatabase::class);

function syncAs(TestCase $test, User $user): TestCase
{
    return $test->actingAs($user)->withHeader('X-Observing-Account', ObservingAccountScope::forUser($user));
}

function syncFixture(): array
{
    return json_decode(file_get_contents(base_path('tests/fixtures/observing-sync/payload.json')), true, flags: JSON_THROW_ON_ERROR);
}

function syncUpload(int $revision = 0, ?array $payload = null): array
{
    return ['expectedRevision' => $revision, 'payload' => $payload ?? syncFixture()];
}

it('requires a session owner and returns private JSON even without an Accept header', function () {
    $this->get('/account/observing-workspace')->assertUnauthorized()->assertExactJson(['error' => 'authentication_required'])
        ->assertHeader('Cache-Control', 'no-store, private')->assertHeader('Referrer-Policy', 'no-referrer');
    $this->putJson('/account/observing-workspace', syncUpload())->assertUnauthorized();
    $this->assertDatabaseCount('private_observing_workspaces', 0);
});

it('does not create or upload anything on login or download of an empty account', function () {
    syncAs($this, User::factory()->create())->getJson('/account/observing-workspace')
        ->assertJson(['revision' => 0, 'state' => 'empty', 'payload' => null]);
    $this->assertDatabaseCount('private_observing_workspaces', 0);
});

it('round trips complete owner data while encrypting it at rest and hiding it from model serialization', function () {
    $owner = User::factory()->create();
    syncAs($this, $owner)->putJson('/account/observing-workspace', syncUpload())
        ->assertOk()->assertExactJson(['revision' => 1, 'state' => 'saved'])->assertHeader('Referrer-Policy', 'no-referrer');
    $download = $this->getJson('/account/observing-workspace')->assertOk()->assertJsonPath('revision', 1)->assertJsonPath('state', 'saved');
    expect($download->json('payload'))->toEqual(syncFixture());
    $raw = DB::table('private_observing_workspaces')->sole()->payload;
    expect($raw)->not->toContain('Private fixture notes')->not->toContain('Europe/London')->not->toContain('51.51');
    expect(PrivateObservingWorkspace::sole()->toArray())->not->toHaveKey('payload');
    $owner->delete();
    $this->assertDatabaseCount('private_observing_workspaces', 0);
});

it('isolates downloads updates and deletion between two authenticated owners', function () {
    $alice = User::factory()->create();
    $bob = User::factory()->create();
    syncAs($this, $alice)->putJson('/account/observing-workspace', syncUpload())->assertOk();
    syncAs($this, $bob)->getJson('/account/observing-workspace')->assertJsonPath('state', 'empty');
    $this->deleteJson('/account/observing-workspace', ['expectedRevision' => 1])->assertConflict();
    $other = syncFixture();
    $other['journal']['observations'][0]['notes'] = 'Bob private notes';
    $this->putJson('/account/observing-workspace', syncUpload(0, $other))->assertOk();
    syncAs($this, $alice)->getJson('/account/observing-workspace')->assertJsonPath('payload.journal.observations.0.notes', syncFixture()['journal']['observations'][0]['notes']);
    $this->deleteJson('/account/observing-workspace', ['expectedRevision' => 1])->assertOk();
    syncAs($this, $bob)->getJson('/account/observing-workspace')->assertJsonPath('payload.journal.observations.0.notes', 'Bob private notes');
});

it('uses revision compare-and-swap and retained deletion tombstones rather than last-write-wins', function () {
    syncAs($this, User::factory()->create());
    $this->putJson('/account/observing-workspace', syncUpload())->assertJsonPath('revision', 1);
    $this->putJson('/account/observing-workspace', syncUpload())->assertConflict()->assertExactJson(['error' => 'revision_conflict']);
    $next = syncFixture();
    $next['journal']['observations'][0]['notes'] = 'Accepted revision two';
    $this->putJson('/account/observing-workspace', syncUpload(1, $next))->assertJsonPath('revision', 2);
    $this->deleteJson('/account/observing-workspace', ['expectedRevision' => 1])->assertConflict();
    $this->deleteJson('/account/observing-workspace', ['expectedRevision' => 2])->assertExactJson(['revision' => 3, 'state' => 'deleted']);
    $this->getJson('/account/observing-workspace')->assertJson(['revision' => 3, 'state' => 'deleted', 'payload' => null]);
    $this->putJson('/account/observing-workspace', syncUpload(2))->assertConflict();
    $this->putJson('/account/observing-workspace', syncUpload(0))->assertConflict();
    expect(DB::table('private_observing_workspaces')->sole()->payload)->toBeNull();
    $this->putJson('/account/observing-workspace', syncUpload(3))->assertJsonPath('revision', 4);
});

it('deletes a never-synced account at revision one so an old first upload conflicts', function () {
    syncAs($this, User::factory()->create());
    $this->deleteJson('/account/observing-workspace', ['expectedRevision' => 0])->assertJsonPath('revision', 1);
    $this->putJson('/account/observing-workspace', syncUpload())->assertConflict();
});

it('does not create a tombstone from an impossible nonzero revision', function () {
    syncAs($this, User::factory()->create())->putJson('/account/observing-workspace', syncUpload(5))->assertConflict();
    $this->assertDatabaseCount('private_observing_workspaces', 0);
});

it('enforces CSRF on both mutation methods and accepts the normal session header', function () {
    $this->app->instance('env', 'local');
    syncAs($this, User::factory()->create());
    $this->putJson('/account/observing-workspace', syncUpload())->assertStatus(419)->assertExactJson(['error' => 'session_expired'])
        ->assertHeader('Referrer-Policy', 'no-referrer')->assertHeader('Cache-Control', 'no-store, private');
    $this->deleteJson('/account/observing-workspace', ['expectedRevision' => 0])->assertStatus(419);
    $this->withSession(['_token' => 'fixture-csrf-token'])->withHeader('X-CSRF-TOKEN', 'fixture-csrf-token')
        ->putJson('/account/observing-workspace', syncUpload())->assertOk();
});

it('rejects malformed private data without changing prior data or echoing logging or flashing it', function (Closure $mutate) {
    Log::spy();
    syncAs($this, User::factory()->create())->putJson('/account/observing-workspace', syncUpload())->assertOk();
    $before = DB::table('private_observing_workspaces')->sole();
    $input = syncUpload(1);
    $mutate($input);
    $this->putJson('/account/observing-workspace', $input)->assertUnprocessable()->assertExactJson(['error' => 'invalid_document'])
        ->assertSessionMissing('_old_input')->assertSessionMissing('errors');
    expect(DB::table('private_observing_workspaces')->sole()->payload)->toBe($before->payload);
    expect(DB::table('private_observing_workspaces')->sole()->revision)->toBe(1);
    Log::shouldNotHaveReceived('error');
    Log::shouldNotHaveReceived('warning');
})->with([
    'owner injection' => [function (&$i) {
        $i['user_id'] = 999;
    }],
    'revision string' => [function (&$i) {
        $i['expectedRevision'] = '1';
    }],
    'revision negative' => [function (&$i) {
        $i['expectedRevision'] = -1;
    }],
    'unsafe revision' => [function (&$i) {
        $i['expectedRevision'] = SyncPayload::MAX_REVISION + 1;
    }],
    'unknown workspace version' => [function (&$i) {
        $i['payload']['equipmentWorkspace']['schemaVersion'] = 3;
    }],
    'wrong list shape' => [function (&$i) {
        $i['payload']['equipmentWorkspace']['equipment'] = new stdClass;
    }],
    'wrong object shape' => [function (&$i) {
        $i['payload']['journal'] = [];
    }],
    'unknown equipment field' => [function (&$i) {
        $i['payload']['equipmentWorkspace']['equipment'][0]['secret'] = 'private-input';
    }],
    'numeric string' => [function (&$i) {
        $i['payload']['equipmentWorkspace']['equipment'][0]['apertureMm'] = '200';
    }],
    'camera pixels beyond sensor' => [function (&$i) {
        $i['payload']['equipmentWorkspace']['equipment'][5]['sensorWidthMm'] = 0.01;
        $i['payload']['equipmentWorkspace']['equipment'][5]['pixelSizeUm'] = 1000;
    }],
    'duplicate UUID' => [function (&$i) {
        $i['payload']['equipmentWorkspace']['sites'][0]['id'] = $i['payload']['equipmentWorkspace']['equipment'][0]['id'];
    }],
    'dangling active site' => [function (&$i) {
        $i['payload']['equipmentWorkspace']['activeSiteId'] = '00000000-0000-4000-8000-000000000099';
    }],
    'latitude above pole' => [function (&$i) {
        $i['payload']['equipmentWorkspace']['sites'][0]['latitude'] = 90.001;
    }],
    'duplicate north directions' => [function (&$i) {
        $i['payload']['equipmentWorkspace']['sites'][0]['horizonMask'] = [['azimuthDeg' => 0, 'minAltitudeDeg' => 0], ['azimuthDeg' => 360, 'minAltitudeDeg' => 2]];
    }],
    'unknown timezone' => [function (&$i) {
        $i['payload']['equipmentWorkspace']['sites'][0]['timezone'] = 'Moon/Unknown';
    }],
    'astral name length' => [function (&$i) {
        $i['payload']['equipmentWorkspace']['equipment'][0]['name'] = str_repeat('🪐', 51);
    }],
    'astral notes length' => [function (&$i) {
        $i['payload']['journal']['observations'][0]['notes'] = str_repeat('🪐', 2001);
    }],
    'date rollover' => [function (&$i) {
        $i['payload']['journal']['observations'][0]['observedAtUtc'] = '2026-02-30T00:00:00Z';
    }],
    'unknown outcome' => [function (&$i) {
        $i['payload']['journal']['observations'][0]['outcome'] = 'guaranteed';
    }],
    'notes controls' => [function (&$i) {
        $i['payload']['journal']['observations'][0]['notes'] = "secret\0note";
    }],
    'target URL' => [function (&$i) {
        $i['payload']['journal']['observations'][0]['target']['id'] = 'https://example.org';
    }],
    'duplicate list target' => [function (&$i) {
        $item = $i['payload']['journal']['lists'][0]['items'][0];
        $item['id'] = '00000000-0000-4000-8000-000000000098';
        $i['payload']['journal']['lists'][0]['items'][] = $item;
    }],
]);

it('bounds actual streamed bytes before global JSON transforms, even without content length', function () {
    syncAs($this, User::factory()->create());
    $this->call('PUT', '/account/observing-workspace', [], [], [], ['CONTENT_TYPE' => 'application/json'], str_repeat(' ', PrivateObservingSync::MAX_BYTES + 1))
        ->assertStatus(413)->assertExactJson(['error' => 'request_too_large'])->assertHeader('Referrer-Policy', 'no-referrer');
    $this->call('PUT', '/account/observing-workspace', [], [], [], ['CONTENT_TYPE' => 'application/json'], '{"secret":broken}')
        ->assertUnprocessable()->assertExactJson(['error' => 'invalid_document']);
    $this->put('/account/observing-workspace', ['private' => 'form data'])->assertStatus(415);
    $this->assertDatabaseCount('private_observing_workspaces', 0);
});

it('preserves empty notes and JavaScript whitespace semantics instead of applying global null conversion', function () {
    $payload = syncFixture();
    $payload['journal']['observations'][0]['notes'] = '';
    $payload['equipmentWorkspace']['equipment'][0]['name'] = "\u{feff}  My telescope\u{00a0}";
    $payload['equipmentWorkspace']['sites'][0]['longitude'] = -1.125;
    $payload['journal']['observations'][0]['timezone'] = 'Asia/Calcutta';
    syncAs($this, User::factory()->create())->putJson('/account/observing-workspace', syncUpload(0, $payload))->assertOk();
    $this->getJson('/account/observing-workspace')->assertJsonPath('payload.journal.observations.0.notes', '')
        ->assertJsonPath('payload.equipmentWorkspace.equipment.0.name', 'My telescope')
        ->assertJsonPath('payload.equipmentWorkspace.sites.0.longitude', -1.12)
        ->assertJsonPath('payload.journal.observations.0.timezone', 'Asia/Calcutta');
});

it('returns generic unavailable on damaged ciphertext without leaking or destroying it', function () {
    syncAs($this, User::factory()->create())->putJson('/account/observing-workspace', syncUpload())->assertOk();
    DB::table('private_observing_workspaces')->update(['payload' => 'private-damaged-ciphertext']);
    $this->getJson('/account/observing-workspace')->assertStatus(503)->assertExactJson(['error' => 'storage_unavailable'])
        ->assertHeader('Cache-Control', 'no-store, private')->assertHeader('Referrer-Policy', 'no-referrer');
    expect(DB::table('private_observing_workspaces')->sole()->payload)->toBe('private-damaged-ciphertext');
});

it('refuses query-string data and unsupported methods with private JSON', function () {
    syncAs($this, User::factory()->create())->get('/account/observing-workspace?user_id=999')->assertBadRequest();
    $this->postJson('/account/observing-workspace', [])->assertStatus(405)->assertHeader('Referrer-Policy', 'no-referrer');
});

it('binds every private request to the account rendered on the page across session switches', function () {
    $alice = User::factory()->create();
    $bob = User::factory()->create();
    syncAs($this, $alice)->putJson('/account/observing-workspace', syncUpload())->assertOk();
    $scope = ObservingAccountScope::forUser($alice);
    $this->getJson('/account/observing-workspace')->assertJsonPath('accountScope', $scope);
    $this->actingAs($bob); // Deliberately keep Alice's page header after the session changes.
    $this->getJson('/account/observing-workspace')->assertConflict()->assertExactJson(['error' => 'account_changed']);
    $this->putJson('/account/observing-workspace', syncUpload())->assertConflict()->assertExactJson(['error' => 'account_changed']);
    $this->deleteJson('/account/observing-workspace', ['expectedRevision' => 0])->assertConflict()->assertExactJson(['error' => 'account_changed']);
    $this->assertDatabaseCount('private_observing_workspaces', 1);
    $this->withHeader('X-Observing-Account', '')->getJson('/account/observing-workspace')->assertConflict();
    syncAs($this, $bob)->getJson('/account/observing-workspace')->assertJsonPath('state', 'empty');
});

it('accepts historical version-one setup snapshots and normalizes them without inventing a horizon', function () {
    $payload = syncFixture();
    $payload['journal']['observations'][0]['equipmentAndSite'] = json_decode(file_get_contents(base_path('tests/fixtures/observing/workspace-v1.json')), true);
    syncAs($this, User::factory()->create())->putJson('/account/observing-workspace', syncUpload(0, $payload))->assertOk();
    $this->getJson('/account/observing-workspace')->assertJsonPath('payload.journal.observations.0.equipmentAndSite.schemaVersion', 2)
        ->assertJsonPath('payload.journal.observations.0.equipmentAndSite.sites.0.horizonMask', null);
});

it('rejects offset-only journal zones consistently with the documented IANA field', function () {
    $payload = syncFixture();
    $payload['journal']['observations'][0]['timezone'] = '+01:00';
    syncAs($this, User::factory()->create())->putJson('/account/observing-workspace', syncUpload(0, $payload))->assertUnprocessable();
    $this->assertDatabaseCount('private_observing_workspaces', 0);
});

it('enforces each canonical document limit within the larger transport envelope', function () {
    syncAs($this, User::factory()->create());
    $largeJournal = syncFixture();
    $observation = $largeJournal['journal']['observations'][0];
    $observation['equipmentAndSite'] = null;
    $observation['notes'] = str_repeat('n', 3900);
    $largeJournal['journal']['observations'] = [];
    for ($i = 100; $i < 390; $i++) {
        $largeJournal['journal']['observations'][] = [...$observation, 'id' => sprintf('00000000-0000-4000-8000-%012d', $i)];
    }
    expect(strlen(json_encode(syncUpload(0, $largeJournal))))->toBeLessThan(PrivateObservingSync::MAX_BYTES);
    $this->putJson('/account/observing-workspace', syncUpload(0, $largeJournal))->assertUnprocessable();

    $largeWorkspace = syncFixture();
    $site = $largeWorkspace['equipmentWorkspace']['sites'][0];
    $site['horizonMask'] = array_map(fn ($i) => ['azimuthDeg' => $i * 5, 'minAltitudeDeg' => 30], range(0, 71));
    $largeWorkspace['equipmentWorkspace']['sites'] = [];
    $largeWorkspace['equipmentWorkspace']['activeSiteId'] = null;
    for ($i = 100; $i < 200; $i++) {
        $largeWorkspace['equipmentWorkspace']['sites'][] = [...$site, 'id' => sprintf('00000000-0000-4000-8000-%012d', $i)];
    }
    expect(strlen(json_encode(syncUpload(0, $largeWorkspace))))->toBeLessThan(PrivateObservingSync::MAX_BYTES);
    $this->putJson('/account/observing-workspace', syncUpload(0, $largeWorkspace))->assertUnprocessable();
    $this->assertDatabaseCount('private_observing_workspaces', 0);
});

it('downloads valid Unicode-heavy journals within the browser response byte limit', function () {
    syncAs($this, User::factory()->create());
    $payload = syncFixture();
    $observation = $payload['journal']['observations'][0];
    $observation['equipmentAndSite'] = null;
    $observation['notes'] = str_repeat('🪐', 2000);
    $payload['journal']['observations'] = [];
    for ($i = 100; $i < 200; $i++) {
        $payload['journal']['observations'][] = [...$observation, 'id' => sprintf('00000000-0000-4000-8000-%012d', $i)];
    }
    // Match browser JSON.stringify, rather than the test helper's ASCII escapes.
    $raw = json_encode(syncUpload(0, $payload), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    $this->call('PUT', '/account/observing-workspace', [], [], [], [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_X_OBSERVING_ACCOUNT' => ObservingAccountScope::forUser(auth()->user()),
    ], $raw)->assertOk();
    $download = $this->getJson('/account/observing-workspace')->assertOk();
    expect(strlen($download->getContent()))->toBeLessThan(PrivateObservingSync::MAX_BYTES);
    expect($download->json('payload.journal.observations.99.notes'))->toBe($observation['notes']);
});
