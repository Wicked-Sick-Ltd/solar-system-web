<?php

declare(strict_types=1);

use App\Services\Changelog\ChangelogNotes;

beforeEach(function () {
    $this->notes = new ChangelogNotes;
});

it('parses conventional commit types and hides chore, ci and dependency noise', function () {
    expect($this->notes->parse('feat(exoplanets): add catalogue pages and galaxy explorer'))
        ->toMatchArray(['type' => 'feat', 'scope' => 'exoplanets', 'subject' => 'add catalogue pages and galaxy explorer'])
        ->and($this->notes->parse('fix!: correct a measurement'))->toMatchArray(['type' => 'fix', 'scope' => null, 'subject' => 'correct a measurement'])
        ->and($this->notes->hidden($this->notes->parse('chore(deps): Bump vite'), 'chore(deps): Bump vite'))->toBeTrue()
        ->and($this->notes->hidden($this->notes->parse('ci: add Larastan'), 'ci: add Larastan'))->toBeTrue()
        ->and($this->notes->hidden($this->notes->parse('fix(deps): bump shell-quote'), 'fix(deps): bump shell-quote'))->toBeTrue()
        ->and($this->notes->hidden($this->notes->parse('Laravel 13.35.0 Shift'), 'Laravel 13.35.0 Shift'))->toBeTrue()
        ->and($this->notes->hidden($this->notes->parse('feat(analytics): add consent-gated Google Analytics 4'), 'feat(analytics): add consent-gated Google Analytics 4'))->toBeFalse();
});

it('writes human friendly notes grouped by day and drops secrets', function () {
    $token = 'ghp_'.str_repeat('a', 36);
    $document = $this->notes->document('Wicked-Sick-Ltd/solar-system-web', [
        ['number' => 110, 'title' => 'fix(objects): serve provisional moon ids that contain a slash', 'body' => 'The public sitemap lists those moon URLs and they 404.', 'merged_at' => '2026-10-10T13:32:24Z'],
        ['number' => 108, 'title' => 'feat(analytics): add consent-gated Google Analytics 4', 'body' => 'It stays off until someone opts in.', 'merged_at' => '2026-10-09T21:01:49Z'],
        ['number' => 106, 'title' => "fix(objects): show Pluto's catalogue discovery date", 'body' => 'The catalogue discovery date is 18 February 1930.', 'merged_at' => '2026-10-09T07:30:05Z'],
        ['number' => 51, 'title' => 'feat(exoplanets): add catalogue pages and galaxy explorer', 'body' => 'Keyboard and touch controls and a linked-list fallback.', 'merged_at' => '2026-09-22T14:36:38Z'],
        ['number' => 103, 'title' => 'chore(deps): Bump the github-actions group', 'body' => 'Dependency bump.', 'merged_at' => '2026-10-07T11:10:47Z'],
        ['number' => 15, 'title' => 'ci: advisory Lighthouse audit', 'body' => 'CI only.', 'merged_at' => '2026-06-02T13:24:53Z'],
        ['number' => 9, 'title' => 'feat: leak '.$token, 'body' => 'Do not publish.', 'merged_at' => '2026-06-02T12:30:04Z'],
    ], [], new DateTimeImmutable('2026-10-10T15:00:00Z'));

    $entries = collect($document['groups'])->flatMap(fn (array $group): array => $group['entries'])->keyBy('number');

    expect($document['groups'][0]['date'])->toBe('2026-10-10')
        ->and($document['groups'][0]['version'])->toBeNull()
        ->and($document['groups'][0]['entries'][0]['summary'])->toBe('Provisional moon pages listed in the sitemap now open, including designations that contain a slash.')
        ->and($document['groups'][0]['entries'][0]['url'])->toBe('https://github.com/Wicked-Sick-Ltd/solar-system-web/pull/110')
        ->and($document['groups'][1]['date'])->toBe('2026-10-09')
        ->and(collect($document['groups'][1]['entries'])->pluck('number')->all())->toBe([108, 106])
        ->and($entries[108]['summary'])->toContain('consent')
        ->and($entries[106]['summary'])->toContain("Pluto's page")
        ->and($entries[51]['summary'])->toContain('galaxy map')
        ->and($entries[51]['summary'])->toContain('accessible')
        ->and($entries->keys()->all())->not->toContain(103)
        ->and(json_encode($document))->not->toContain($token);

    expect(fn () => $this->notes->document('../secrets', []))->toThrow(RuntimeException::class);
});

it('groups notes by a published release and leaves later merges on their days', function () {
    $document = $this->notes->document('Wicked-Sick-Ltd/solar-system-web', [
        ['number' => 2, 'title' => 'feat: add a sky chart', 'merged_at' => '2026-05-02T00:00:00Z'],
        ['number' => 3, 'title' => 'fix: correct a label', 'merged_at' => '2026-06-01T00:00:00Z'],
    ], [
        ['tag' => 'v1.2.0', 'published_at' => '2026-05-20T00:00:00Z'],
        ['tag' => 'v1.3.0-rc.1', 'published_at' => '2026-05-25T00:00:00Z', 'prerelease' => true],
    ]);

    expect($document['groups'][0]['date'])->toBe('2026-06-01')
        ->and($document['groups'][0]['version'])->toBeNull()
        ->and($document['groups'][0]['entries'][0]['number'])->toBe(3)
        ->and($document['groups'][1]['version'])->toBe('1.2.0')
        ->and($document['groups'][1]['entries'][0]['number'])->toBe(2);
});
