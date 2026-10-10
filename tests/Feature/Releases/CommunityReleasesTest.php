<?php

use App\Models\CommunityRelease;
use App\Services\Releases\ReleasePublisher;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\Process\Process;

uses(RefreshDatabase::class);

function releaseNotes(string $title = 'Explore Public Universe'): array
{
    return ['title' => $title, 'summary' => 'A clearer view of astronomical data.', 'sections' => ['Explore' => ['Find known worlds and their measurements.']]];
}

beforeEach(function () {
    $this->notesPath = sys_get_temp_dir().'/universe-release-tests-'.bin2hex(random_bytes(8));
    File::makeDirectory($this->notesPath);
    config(['releases.version' => '1.0.0', 'releases.commit' => str_repeat('a', 40), 'releases.notes_path' => $this->notesPath, 'app.url' => 'https://publicuniverse.test']);
    File::put($this->notesPath.'/1.0.0.json', json_encode(releaseNotes()));
    Http::preventStrayRequests();
});

afterEach(fn () => File::deleteDirectory($this->notesPath));

function fakeReleaseReady(): void
{
    Http::fake(['https://publicuniverse.test/up/release*' => Http::response(['version' => config('releases.version'), 'commit' => config('releases.commit'), 'database_ready' => true], 200, ['Cache-Control' => 'no-store, private'])]);
}

it('publishes only after the exact HTTPS build and database readiness response', function () {
    fakeReleaseReady();
    $this->artisan('universe:releases:publish', ['--commit' => str_repeat('a', 40)])->assertSuccessful();
    $this->assertDatabaseHas('community_releases', ['version' => '1.0.0', 'commit' => str_repeat('a', 40)]);
    Http::assertSent(fn ($request) => $request->method() === 'GET' && $request->hasHeader('Cache-Control', 'no-store, no-cache')
        && preg_match('/^[a-f0-9]{32}$/D', $request['check']) === 1);
    Http::assertSentCount(1);
});

it('freezes the first commit notes and date when repeated or a concurrent writer wins', function () {
    fakeReleaseReady();
    $publisher = app(ReleasePublisher::class);
    $original = $publisher->publish(str_repeat('a', 40));
    File::put($this->notesPath.'/1.0.0.json', json_encode(releaseNotes('Changed draft')));
    $this->travel(1)->days();
    $repeated = $publisher->publish(str_repeat('a', 40));
    expect($repeated->id)->toBe($original->id)->and($repeated->notes)->toBe($original->notes)
        ->and($repeated->published_at->toIso8601String())->toBe($original->published_at->toIso8601String());
    expect(CommunityRelease::count())->toBe(1);
    expect(fn () => CommunityRelease::create(['version' => '1.0.0', 'commit' => str_repeat('b', 40), 'notes' => releaseNotes('Race loser'), 'published_at' => now()]))->toThrow(QueryException::class);
    expect(CommunityRelease::sole()->notes['title'])->toBe('Explore Public Universe');
});

it('keeps one immutable ledger entry when independent publishers run concurrently', function () {
    $database = $this->notesPath.'/concurrency.sqlite';
    touch($database);
    config(['database.connections.release_race' => ['driver' => 'sqlite', 'database' => $database, 'busy_timeout' => 5000]]);
    Schema::connection('release_race')->create('community_releases', function ($table) {
        $table->id();
        $table->string('version')->unique();
        $table->string('commit');
        $table->json('notes');
        $table->timestamp('published_at');
    });
    $script = <<<'PHP'
<?php
require $argv[1].'/vendor/autoload.php';
$app = require $argv[1].'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
config(['database.default'=>'sqlite','database.connections.sqlite.database'=>$argv[2],
'database.connections.sqlite.busy_timeout'=>5000,'database.connections.sqlite.foreign_key_constraints'=>false,
'cache.default'=>'array','releases.notes_path'=>$argv[3],'releases.version'=>'1.0.0',
'releases.commit'=>str_repeat('a',40),'app.url'=>'https://publicuniverse.test']);
Illuminate\Support\Facades\Http::preventStrayRequests();
Illuminate\Support\Facades\Http::fake(['*'=>Illuminate\Support\Facades\Http::response([
'version'=>'1.0.0','commit'=>str_repeat('a',40),'database_ready'=>true],200,['Cache-Control'=>'no-store'])]);
$app->make(App\Services\Releases\ReleasePublisher::class)->publish(str_repeat('a',40));
PHP;
    $path = $this->notesPath.'/publisher.php';
    File::put($path, $script);
    $processes = [];
    try {
        foreach (range(1, 2) as $i) {
            $process = new Process([PHP_BINARY, $path, base_path(), $database, $this->notesPath]);
            $process->start();
            $processes[] = $process;
        }
        foreach ($processes as $process) {
            $process->wait();
            expect($process->isSuccessful())->toBeTrue($process->getErrorOutput());
        }
        expect(DB::connection('release_race')->table('community_releases')->count())->toBe(1)
            ->and(DB::connection('release_race')->table('community_releases')->value('commit'))->toBe(str_repeat('a', 40));
    } finally {
        DB::purge('release_race');
    }
});

it('uses a fresh nonce for every readiness check and refuses redirects', function () {
    $optionsSeen = [];
    Http::fake(function ($request, $options) use (&$optionsSeen) {
        $optionsSeen[] = $options;

        return Http::response(['version' => '1.0.0', 'commit' => str_repeat('a', 40), 'database_ready' => true], 302,
            ['Location' => 'https://other.test/up/release', 'Cache-Control' => 'no-store']);
    });
    for ($i = 0; $i < 2; $i++) {
        $this->artisan('universe:releases:publish', ['--commit' => str_repeat('a', 40)])->assertFailed();
    }
    $requests = Http::recorded();
    expect($requests[0][0]['check'])->not->toBe($requests[1][0]['check']);
    expect($optionsSeen[0]['allow_redirects'])->toBeFalse();
    expect(CommunityRelease::count())->toBe(0);
    Http::assertSentCount(2);
});

it('rejects untrusted readiness responses without publishing', function (array|string $body, int $status, array $headers) {
    Http::fake(['*' => Http::response(is_array($body) ? json_encode($body) : $body, $status, $headers + ['Content-Type' => 'application/json'])]);
    $this->artisan('universe:releases:publish', ['--commit' => str_repeat('a', 40)])->assertFailed();
    expect(CommunityRelease::count())->toBe(0);
})->with([
    [['version' => '1.0.1', 'commit' => str_repeat('a', 40), 'database_ready' => true], 200, ['Cache-Control' => 'no-store']],
    [['version' => '1.0.0', 'commit' => str_repeat('b', 40), 'database_ready' => true], 200, ['Cache-Control' => 'no-store']],
    [['version' => '1.0.0', 'commit' => str_repeat('a', 40), 'database_ready' => false], 200, ['Cache-Control' => 'no-store']],
    [['version' => '1.0.0', 'commit' => str_repeat('a', 40), 'database_ready' => true], 503, ['Cache-Control' => 'no-store']],
    [['version' => '1.0.0', 'commit' => str_repeat('a', 40), 'database_ready' => true], 200, []],
    [['version' => '1.0.0', 'commit' => str_repeat('a', 40), 'database_ready' => true], 200, ['Cache-Control' => 'no-store', 'Content-Type' => 'text/html']],
    ['not-json', 200, ['Content-Type' => 'application/json', 'Cache-Control' => 'no-store']],
]);

it('does not expose transport exceptions in console output', function () {
    Http::fake(fn () => throw new ConnectionException('SECRET-TOKEN transport detail'));
    $this->artisan('universe:releases:publish', ['--commit' => str_repeat('a', 40)])
        ->doesntExpectOutputToContain('SECRET-TOKEN')->assertFailed();
});

it('rejects invalid local build identities and unknown versions without network calls', function (string $version, string $commit) {
    config(['releases.version' => $version]);
    $this->artisan('universe:releases:publish', ['--commit' => $commit])->assertFailed();
    Http::assertNothingSent();
})->with([['1.0.0', str_repeat('b', 40)], ['1.0.0', ''], ['1.0.1', str_repeat('a', 40)], ['../1.0.0', str_repeat('a', 40)]]);

it('rejects unsafe APP_URL forms before sending requests', function (string $url) {
    config(['app.url' => $url]);
    $this->artisan('universe:releases:publish', ['--commit' => str_repeat('a', 40)])->assertFailed();
    Http::assertNothingSent();
})->with(['http://publicuniverse.test', 'https://user:secret@publicuniverse.test', 'https://publicuniverse.test?secret=x', 'https://publicuniverse.test#fragment', 'https://publicuniverse.test/subpath']);

it('never publishes the bootstrap sentinel or exposes draft files', function () {
    config(['releases.version' => '0.0.0']);
    fakeReleaseReady();
    $this->artisan('universe:releases:publish', ['--commit' => str_repeat('a', 40)])->assertSuccessful();
    config(['changelog.path' => $this->notesPath.'/absent-changelog.json']);
    $this->get('/whats-new')->assertOk()->assertSee('Community preview')->assertDontSee('Explore Public Universe');
    $this->get('/releases')->assertOk()->assertSee('Community preview')->assertDontSee('Explore Public Universe');
    $this->get('/whats-new/1.0.0')->assertNotFound();
    expect(CommunityRelease::count())->toBe(0);
    Http::assertSentCount(1);
});

it('renders labelled drafts without publishing or sending', function () {
    $this->artisan('universe:releases:render', ['version' => '1.0.0'])->expectsOutputToContain('DRAFT — unreleased, for review only')->assertSuccessful();
    $this->artisan('universe:releases:render', ['version' => '1.0.0', '--format' => 'json'])->expectsOutputToContain('"version": "1.0.0"')->assertSuccessful();
    $this->artisan('universe:releases:render', ['version' => '../secrets'])->assertFailed();
    $this->artisan('universe:releases:render', ['version' => '1.0.0', '--format' => 'html'])->assertFailed();
    expect(CommunityRelease::count())->toBe(0);
    Http::assertNothingSent();
});

it('keeps publication blocked with a missing ledger but permits local draft rendering', function () {
    Schema::drop('community_releases');
    fakeReleaseReady();
    $this->artisan('universe:releases:publish', ['--commit' => str_repeat('a', 40)])->assertFailed();
    $this->artisan('universe:releases:render', ['version' => '1.0.0'])->expectsOutputToContain('DRAFT')->assertSuccessful();
    $this->get('/up/release')->assertStatus(503)->assertJsonPath('database_ready', false)
        ->assertJsonStructure(['version', 'commit', 'database_ready'])->assertDontSee('SQLSTATE');
});

it('reports only cached identity and database readiness with no cache storage', function () {
    $this->get('/up/release')->assertOk()->assertExactJson(['version' => '1.0.0', 'commit' => str_repeat('a', 40), 'database_ready' => true])
        ->assertHeader('Cache-Control', 'no-store, private')->assertHeader('Pragma', 'no-cache');
    config(['releases.commit' => 'secret-like-invalid-build']);
    $this->get('/up/release')->assertStatus(503)->assertJsonPath('commit', null)->assertDontSee('secret-like-invalid-build');
});

it('shows only durable published history supported by the running version', function () {
    foreach (['1.0.0', '1.2.0', '1.10.0', '2.0.0'] as $version) {
        CommunityRelease::create(['version' => $version, 'commit' => str_repeat('a', 40), 'notes' => releaseNotes('Release '.$version), 'published_at' => now()]);
    }
    config(['releases.version' => '1.10.0']);
    $this->get('/whats-new')->assertOk()->assertSeeInOrder(['Release 1.10.0', 'Release 1.2.0', 'Release 1.0.0'])->assertDontSee('Release 2.0.0');
    $this->get('/whats-new/1.2.0')->assertOk()->assertSee('Release 1.2.0')->assertDontSee('Release 1.10.0');
    config(['releases.version' => '1.0.0']);
    $this->get('/whats-new/1.2.0')->assertNotFound();
    $this->get('/whats-new/1.0.0')->assertOk();
    expect(CommunityRelease::count())->toBe(4);
});

it('escapes ledger content even if it came from an older unsafe writer', function () {
    $notes = releaseNotes('<script>alert(1)</script>');
    $notes['sections'] = ['<img src=x onerror=alert(1)>' => ['<script>bad()</script>']];
    CommunityRelease::create(['version' => '1.0.0', 'commit' => str_repeat('a', 40), 'notes' => $notes, 'published_at' => now()]);
    $this->get('/whats-new/1.0.0')->assertOk()->assertSee($notes['title'])->assertDontSee($notes['title'], false)->assertDontSee('<img src=x', false);
});

it('rejects malformed or oversized review notes', function (string $payload) {
    File::put($this->notesPath.'/1.0.0.json', $payload);
    $this->artisan('universe:releases:render', ['version' => '1.0.0'])->assertFailed();
    $this->artisan('universe:releases:publish', ['--commit' => str_repeat('a', 40)])->assertFailed();
    Http::assertNothingSent();
})->with([
    'broken JSON' => '{', 'scalar' => '"title"', 'unknown field' => json_encode(releaseNotes() + ['send' => true]),
    'HTML' => json_encode(releaseNotes('<b>Title</b>')), 'newline' => json_encode(releaseNotes("Title\nInjected")),
    'empty' => json_encode(releaseNotes('   ')), 'long title' => json_encode(releaseNotes(str_repeat('x', 121))),
    'oversize' => str_repeat(' ', 32769),
]);

it('fails preview deployment when exact readiness cannot be established', function () {
    config(['releases.version' => '0.0.0']);
    Http::fake(['*' => Http::response([], 503)]);
    $this->artisan('universe:releases:publish', ['--commit' => str_repeat('a', 40)])->assertFailed();
    expect(CommunityRelease::count())->toBe(0);
});
