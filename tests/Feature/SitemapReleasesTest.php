<?php

declare(strict_types=1);

use App\Models\CommunityRelease;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(fn () => fakeSolar());

it('lists published release notes and omits versions the site would 404', function () {
    config(['releases.version' => '1.2.0']);
    $published = CarbonImmutable::parse('2026-08-01T15:04:05Z');
    CommunityRelease::create([
        'version' => '1.0.0',
        'commit' => str_repeat('a', 40),
        'notes' => ['title' => 'Explore Public Universe', 'summary' => 'A clearer view of astronomical data.', 'sections' => ['Explore' => ['Find known worlds.']]],
        'published_at' => $published,
    ]);
    CommunityRelease::create([
        'version' => '9.9.9',
        'commit' => str_repeat('b', 40),
        'notes' => ['title' => 'Not shipped', 'summary' => 'This version is newer than the running build.', 'sections' => ['Later' => ['Do not index it.']]],
        'published_at' => $published,
    ]);

    $xml = $this->get('/sitemaps/releases.xml')->assertOk()->getContent();

    expect($xml)->toContain('<loc>'.url('/releases/1.0.0').'</loc>')
        ->and($xml)->toContain('<lastmod>2026-08-01T15:04:05Z</lastmod>')
        ->and($xml)->not->toContain('/releases/9.9.9');

    $this->get('/releases/1.0.0')->assertOk();
    $this->get('/releases/9.9.9')->assertNotFound();
});
