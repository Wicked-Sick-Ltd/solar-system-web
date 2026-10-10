<?php

declare(strict_types=1);

use App\Services\Changelog\ChangelogCatalog;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;

function changelogFixture(array $pulls, array $releases = []): string
{
    $path = sys_get_temp_dir().'/universe-changelog-'.bin2hex(random_bytes(6)).'.json';
    File::put($path, json_encode(['pulls' => $pulls, 'releases' => $releases], JSON_THROW_ON_ERROR));

    return $path;
}

function withoutGithubCredentials(callable $callback): void
{
    $saved = [];
    foreach (['GITHUB_TOKEN', 'GH_TOKEN'] as $name) {
        $saved[$name] = getenv($name);
        putenv($name);
        unset($_ENV[$name], $_SERVER[$name]);
    }
    try {
        $callback();
    } finally {
        foreach ($saved as $name => $value) {
            if (is_string($value) && $value !== '') {
                putenv($name.'='.$value);
                $_ENV[$name] = $value;
                $_SERVER[$name] = $value;
            }
        }
    }
}

it('renders grouped release notes with dates, pull request links and feeds', function () {
    $path = sys_get_temp_dir().'/universe-notes-'.bin2hex(random_bytes(6)).'.json';
    $input = changelogFixture([
        ['number' => 110, 'title' => 'fix(objects): serve provisional moon ids that contain a slash', 'body' => 'The sitemap links 404.', 'merged_at' => '2026-10-10T13:32:24Z'],
        ['number' => 4, 'title' => 'chore: tidy comments', 'body' => 'Internal.', 'merged_at' => '2026-10-10T12:00:00Z'],
        ['number' => 51, 'title' => 'feat(exoplanets): add catalogue pages and galaxy explorer', 'body' => 'Keyboard controls.', 'merged_at' => '2026-09-22T14:36:38Z'],
    ]);
    $this->artisan('universe:changelog:collect', ['--input' => $input, '--output' => $path, '--repository' => 'Wicked-Sick-Ltd/solar-system-web'])->assertSuccessful();
    config(['changelog.path' => $path]);

    $this->get('/releases')->assertOk()
        ->assertSee('What’s new')
        ->assertSeeInOrder(['10 October 2026', '22 September 2026'])
        ->assertSee('Provisional moon pages listed in the sitemap now open', false)
        ->assertSee('https://github.com/Wicked-Sick-Ltd/solar-system-web/pull/110', false)
        ->assertSee('galaxy map')
        ->assertSee('accessible')
        ->assertSee('id="pr-110"', false)
        ->assertSee('Subscribe with Atom')
        ->assertSee('application/atom+xml', false)
        ->assertDontSee('tidy comments');

    $this->get('/whats-new')->assertOk()->assertSee('Pull request 110');
    $this->get('/releases/feed.atom')->assertOk()
        ->assertHeader('Content-Type', 'application/atom+xml; charset=UTF-8')
        ->assertSee('<feed xmlns="http://www.w3.org/2005/Atom">', false)
        ->assertSee('<author>', false)
        ->assertSee('<name>'.config('site.name').'</name>', false)
        ->assertSee('https://github.com/Wicked-Sick-Ltd/solar-system-web/pull/51', false)
        ->assertDontSee('tidy comments');
    $this->get('/releases/feed.rss')->assertOk()
        ->assertHeader('Content-Type', 'application/rss+xml; charset=UTF-8')
        ->assertSee('<rss version="2.0"', false)
        ->assertSee('<guid isPermaLink="true">https://github.com/Wicked-Sick-Ltd/solar-system-web/pull/110</guid>', false);

    File::delete($path);
    File::delete($input);
});

it('escapes note text in the page and the feed', function () {
    $path = sys_get_temp_dir().'/universe-notes-'.bin2hex(random_bytes(6)).'.json';
    File::put($path, json_encode([
        'repository' => 'Wicked-Sick-Ltd/solar-system-web',
        'generated_at' => '2026-10-10T00:00:00Z',
        'groups' => [[
            'date' => '2026-10-10',
            'version' => null,
            'entries' => [[
                'number' => 7,
                'title' => '<script>alert(1)</script>',
                'type' => 'fix',
                'scope' => null,
                'summary' => '<img src=x onerror=alert(1)>',
                'merged_at' => '2026-10-10T00:00:00Z',
                'url' => 'https://github.com/Wicked-Sick-Ltd/solar-system-web/pull/7',
            ]],
        ]],
    ], JSON_THROW_ON_ERROR));
    config(['changelog.path' => $path]);

    $this->get('/releases')->assertOk()->assertDontSee('<img src=x', false)->assertDontSee('<script>alert(1)</script>', false);
    $this->get('/releases/feed.atom')->assertOk()->assertDontSee('<img src=x', false);

    File::delete($path);
});

it('refuses to collect without a credential and does not store one', function () {
    withoutGithubCredentials(function () {
        $output = sys_get_temp_dir().'/universe-notes-'.bin2hex(random_bytes(6)).'.json';
        $token = 'ghp_'.str_repeat('b', 36);
        Http::preventStrayRequests();
        $this->artisan('universe:changelog:collect', ['--output' => $output])->assertFailed();
        Http::assertNothingSent();

        Http::fake(['https://api.github.com/*' => Http::response([
            ['number' => 8, 'title' => 'feat: add a public chart', 'body' => 'Token '.$token.' must not be copied.', 'merged_at' => '2026-10-01T00:00:00Z'],
        ])]);
        putenv('GITHUB_TOKEN='.$token);
        try {
            $this->artisan('universe:changelog:collect', ['--output' => $output])
                ->doesntExpectOutputToContain($token)
                ->assertSuccessful();
        } finally {
            putenv('GITHUB_TOKEN');
            unset($_ENV['GITHUB_TOKEN'], $_SERVER['GITHUB_TOKEN']);
        }
        expect(File::get($output))->not->toContain($token)->and(File::get($output))->toContain('public chart');
        Http::assertSent(fn ($request): bool => $request->hasHeader('Authorization', 'Bearer '.$token) && $request->url() !== '' && ! str_contains($request->url(), $token));
        File::delete($output);
    });
});

it('checks the committed file without rewriting it', function () {
    $output = sys_get_temp_dir().'/universe-notes-'.bin2hex(random_bytes(6)).'.json';
    $input = changelogFixture([
        ['number' => 12, 'title' => 'feat: add a favicon', 'merged_at' => '2026-06-02T12:43:26Z'],
    ]);
    $this->artisan('universe:changelog:collect', ['--input' => $input, '--output' => $output])->assertSuccessful();
    $this->artisan('universe:changelog:collect', ['--input' => $input, '--output' => $output, '--check' => true])->assertSuccessful();
    File::put($input, json_encode([['number' => 13, 'title' => 'feat: add a second page', 'merged_at' => '2026-06-03T12:43:26Z']], JSON_THROW_ON_ERROR));
    $this->artisan('universe:changelog:collect', ['--input' => $input, '--output' => $output, '--check' => true])->assertFailed();
    expect(File::get($output))->toContain('favicon')->not->toContain('second page');
    File::delete($output);
    File::delete($input);
});

it('ships the real merged highlights and leaves dependency noise out', function () {
    $entries = collect(app(ChangelogCatalog::class)->entries())->keyBy('number');

    expect($entries[51]['summary'])->toContain('galaxy map')
        ->and($entries[51]['summary'])->toContain('accessible')
        ->and($entries[51]['type'])->toBe('feat')
        ->and($entries[106]['summary'])->toContain('Pluto')
        ->and($entries[110]['summary'])->toContain('sitemap')
        ->and($entries[110]['type'])->toBe('fix')
        ->and($entries[108]['summary'])->toContain('consent')
        ->and($entries->keys()->all())->not->toContain(103)
        ->and($entries->keys()->all())->not->toContain(15)
        ->and(app(ChangelogCatalog::class)->groups()[0]['date'])->toBe('2026-10-10');

    $this->get(route('releases.index'))->assertOk()
        ->assertSee('10 October 2026')
        ->assertSee('galaxy map')
        ->assertSee('Pull request 110')
        ->assertSee(route('releases.feed'), false);
});
