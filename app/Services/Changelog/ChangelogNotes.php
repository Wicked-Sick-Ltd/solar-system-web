<?php

declare(strict_types=1);

namespace App\Services\Changelog;

use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use JsonException;
use RuntimeException;

/**
 * Turns merged pull requests into visitor-facing notes grouped by release or day.
 * Dependency, CI and chore updates are omitted. Bodies are not copied into the file.
 */
final class ChangelogNotes
{
    private const HIDDEN_TYPES = ['chore', 'ci', 'deps'];

    private const HIDDEN_SCOPES = ['deps', 'deps-dev', 'ci'];

    /**
     * @param  list<array<string, mixed>>  $pulls
     * @param  list<array<string, mixed>>  $releases
     * @return array{repository:string,generated_at:string,groups:list<array{date:string,version:?string,entries:list<array{number:int,title:string,type:string,scope:?string,summary:string,merged_at:string,url:string}>}>}
     */
    public function document(string $repository, array $pulls, array $releases = [], ?DateTimeInterface $now = null): array
    {
        $repository = $this->repository($repository);
        $entries = [];
        foreach ($pulls as $pull) {
            $entry = $this->entry($repository, $pull);
            if ($entry !== null) {
                $entries[$entry['number']] = $entry;
            }
        }

        $groups = $this->groups(array_values($entries), $this->releaseWindows($releases));
        $generated = DateTimeImmutable::createFromInterface($now ?? new DateTimeImmutable('now', new DateTimeZone('UTC')))
            ->setTimezone(new DateTimeZone('UTC'));

        return [
            'repository' => $repository,
            'generated_at' => $generated->format('Y-m-d\TH:i:s\Z'),
            'groups' => $groups,
        ];
    }

    /**
     * @param  array{repository:string,generated_at:string,groups:list<array{date:string,version:?string,entries:list<array{number:int,title:string,type:string,scope:?string,summary:string,merged_at:string,url:string}>}>}  $document
     */
    public function write(string $path, array $document): void
    {
        $directory = dirname($path);
        if (! is_dir($directory) && ! mkdir($directory, 0775, true) && ! is_dir($directory)) {
            throw new RuntimeException('Could not create the release notes directory.');
        }

        $json = json_encode($document, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        if (file_put_contents($path, $json."\n") === false) {
            throw new RuntimeException('Could not write release notes.');
        }
    }

    /**
     * @param  array{repository:string,groups:list<array<string,mixed>>}  $document
     */
    public function sameEntries(string $path, array $document): bool
    {
        if (! is_file($path)) {
            return false;
        }

        try {
            $existing = json_decode(file_get_contents($path) ?: '', true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return false;
        }

        if (! is_array($existing)) {
            return false;
        }

        return ($existing['repository'] ?? null) === $document['repository']
            && ($existing['groups'] ?? null) === $document['groups'];
    }

    /**
     * @param  array<string, mixed>  $pull
     * @return array{number:int,title:string,type:string,scope:?string,summary:string,merged_at:string,url:string}|null
     */
    public function entry(string $repository, array $pull): ?array
    {
        $repository = $this->repository($repository);
        $number = $pull['number'] ?? null;
        $title = trim((string) ($pull['title'] ?? ''));
        $mergedAt = $this->instant((string) ($pull['merged_at'] ?? $pull['mergedAt'] ?? ''));
        if (! is_int($number) && ! (is_string($number) && preg_match('/^[1-9][0-9]*$/', $number) === 1)) {
            return null;
        }
        $number = (int) $number;
        if ($title === '' || $mergedAt === null || $this->sensitive($title)) {
            return null;
        }

        $parsed = $this->parse($title);
        if ($this->hidden($parsed, $title)) {
            return null;
        }

        $summary = $this->summary($parsed, (string) ($pull['body'] ?? ''));
        if ($summary === '' || $this->sensitive($summary)) {
            return null;
        }

        return [
            'number' => $number,
            'title' => $title,
            'type' => $parsed['type'] ?? 'update',
            'scope' => $parsed['scope'],
            'summary' => $summary,
            'merged_at' => $mergedAt,
            'url' => 'https://github.com/'.$repository.'/pull/'.$number,
        ];
    }

    /**
     * @return array{type:?string,scope:?string,subject:string}
     */
    public function parse(string $title): array
    {
        if (preg_match('/^(?<type>[a-zA-Z]+)(?:\((?<scope>[^)]+)\))?!?: (?<subject>.+)$/', trim($title), $matches) !== 1) {
            return ['type' => null, 'scope' => null, 'subject' => trim($title)];
        }

        $scope = $matches['scope'] !== '' ? $matches['scope'] : null;

        return [
            'type' => strtolower($matches['type']),
            'scope' => $scope,
            'subject' => trim($matches['subject']),
        ];
    }

    /**
     * @param  array{type:?string,scope:?string,subject:string}  $parsed
     */
    public function hidden(array $parsed, string $title): bool
    {
        $type = strtolower((string) ($parsed['type'] ?? ''));
        $scope = strtolower((string) ($parsed['scope'] ?? ''));
        if (in_array($type, self::HIDDEN_TYPES, true)) {
            return true;
        }
        if ($scope !== '' && (in_array($scope, self::HIDDEN_SCOPES, true) || str_contains($scope, 'deps'))) {
            return true;
        }

        return preg_match('/dependabot|\bbump\b|laravel\s+\d+(?:\.\d+)*\s+shift/i', $title) === 1;
    }

    public function repository(string $repository): string
    {
        if (preg_match('/^[A-Za-z0-9][A-Za-z0-9_.-]*\/[A-Za-z0-9][A-Za-z0-9_.-]*$/', $repository) !== 1 || str_contains($repository, '..')) {
            throw new RuntimeException('The repository must be an owner/name.');
        }

        return $repository;
    }

    /**
     * @param  array{type:?string,scope:?string,subject:string}  $parsed
     */
    private function summary(array $parsed, string $body): string
    {
        $body = substr($body, 0, 8000);
        $signalled = $this->signal($parsed, $body);
        $summary = $this->friendlier($signalled ?? $this->polish($parsed));
        $summary = $this->plain($summary);
        if ($summary === '') {
            return '';
        }

        if (preg_match('/moon/i', $parsed['subject']) === 1 && preg_match('/slash/i', $parsed['subject']) === 1 && preg_match('/sitemap/i', $body) === 1) {
            return 'Provisional moon pages listed in the sitemap now open, including designations that contain a slash.';
        }

        if (preg_match('/keyboard/i', $body) === 1 && preg_match('/galaxy/i', $parsed['subject']) === 1 && preg_match('/accessible|keyboard/i', $summary) !== 1) {
            $summary = rtrim($summary, '.').'. The map has keyboard controls and an accessible list of the same systems.';
        } elseif (preg_match('/\baccessible\b/i', $body) === 1 && preg_match('/accessible|keyboard/i', $summary) !== 1) {
            $summary = rtrim($summary, '.').'. The screens include accessible controls.';
        }

        return $this->plain($summary);
    }

    /**
     * @param  array{type:?string,scope:?string,subject:string}  $parsed
     */
    private function signal(array $parsed, string $body): ?string
    {
        $subject = $parsed['subject'];
        if (preg_match('/pluto/i', $subject) === 1 && preg_match('/discovery date/i', $subject."\n".$body) === 1) {
            return "Pluto's page shows the catalogue discovery date.";
        }

        return null;
    }

    /**
     * @param  array{type:?string,scope:?string,subject:string}  $parsed
     */
    private function polish(array $parsed): string
    {
        $subject = trim(preg_replace('/\s+/', ' ', $parsed['subject']) ?? '');
        $subject = preg_replace('/\s*\(P\d+\)/', '', $subject) ?? $subject;
        $replacements = [
            '/\bconsent-gated Google Analytics 4\b/i' => 'Google Analytics that waits for your consent',
            '/\bconsent-gated GA4\b/i' => 'Google Analytics that waits for your consent',
            '/\bgalaxy explorer\b/i' => 'a galaxy map',
            '/\bids\b/' => 'identifiers',
        ];
        foreach ($replacements as $pattern => $replacement) {
            $subject = preg_replace($pattern, $replacement, $subject) ?? $subject;
        }
        $subject = preg_replace('/^(add|adds|adding)\s+/i', '', $subject) ?? $subject;
        $subject = trim($subject);
        if ($parsed['scope'] === 'exoplanets' && preg_match('/exoplanet/i', $subject) !== 1) {
            $subject = preg_replace('/^catalogue pages/i', 'Exoplanet catalogue pages', $subject) ?? $subject;
            if (preg_match('/exoplanet/i', $subject) !== 1) {
                $subject = 'Exoplanets: '.$subject;
            }
        }
        if ($subject === '') {
            return '';
        }

        $first = mb_strtoupper(mb_substr($subject, 0, 1));

        return $first.mb_substr($subject, 1);
    }

    private function friendlier(string $summary): string
    {
        $rewrites = [
            '/^Unblock Forge\'s release lock and composer command$/' => 'Publishing the site no longer gets stuck',
            '/^Surface a failed laravel-bootstrap in the session context$/' => 'A failed startup is reported clearly',
            '/^Clear CodeQL High clear-text storage\/logging in handout generator$/' => 'Handout files no longer leave private text in logs or storage',
            '/^Consolidate observing programme with current main$/' => 'Observing tools are available together',
            '/^Show mass when one is on record$/' => 'Close approaches show a mass when one is on record',
            '/^Exoplanets: preserve small measurement uncertainties$/' => 'Small exoplanet measurement uncertainties stay visible',
            '/^Unify SolarApiClient batch cache handling$/' => 'Catalogue requests share one cache',
            '/^Expand coverage \(Livewire interactions \+ unit helpers\)$/' => 'More of the site is covered by automated checks',
            '/^Production prep for Laravel Forge$/' => 'The site is ready to be hosted for visitors',
            '/^Edge-cacheable cookie-less public pages$/' => 'Public pages can be cached without storing a cookie',
            '/^Per-object OG share cards \(Imagick, S3-backed\)$/' => 'Each object has an image for link previews',
            '/^Reuse cards and pagination$/' => 'Catalogue cards and page controls are shared across lists',
            '/^Refactor object detail page sections$/' => 'Object pages are easier to scan',
        ];
        foreach ($rewrites as $pattern => $replacement) {
            if (preg_match($pattern, $summary) === 1) {
                return $replacement;
            }
        }

        return $summary;
    }

    private function plain(string $text): string
    {
        $text = trim(preg_replace('/\s+/u', ' ', $text) ?? '');
        $text = rtrim($text, ' .').($text === '' ? '' : '.');
        if ($text === '.' || $this->sensitive($text) || preg_match('/[<>]/', $text) === 1) {
            return '';
        }
        if (mb_strlen($text) > 400) {
            $text = rtrim(mb_substr($text, 0, 397)).'…';
        }

        return $text;
    }

    private function sensitive(string $text): bool
    {
        return preg_match('/ghp_[A-Za-z0-9]+|github_pat_[A-Za-z0-9_]+|sk_live_[A-Za-z0-9]+|AKIA[0-9A-Z]{16}|xox[baprs]-[A-Za-z0-9-]+|-----BEGIN [A-Z ]*PRIVATE KEY-----/', $text) === 1;
    }

    /**
     * @param  list<array{number:int,title:string,type:string,scope:?string,summary:string,merged_at:string,url:string}>  $entries
     * @param  list<array{version:string,published_at:string,previous:?string}>  $releases
     * @return list<array{date:string,version:?string,entries:list<array{number:int,title:string,type:string,scope:?string,summary:string,merged_at:string,url:string}>}>
     */
    private function groups(array $entries, array $releases): array
    {
        if ($releases === []) {
            return $this->byDate($entries, null);
        }

        $groups = [];
        $claimed = [];
        foreach (array_reverse($releases) as $release) {
            $window = [];
            foreach ($entries as $entry) {
                if (isset($claimed[$entry['number']])) {
                    continue;
                }
                if ($entry['merged_at'] <= $release['published_at'] && ($release['previous'] === null || $entry['merged_at'] > $release['previous'])) {
                    $window[] = $entry;
                    $claimed[$entry['number']] = true;
                }
            }
            if ($window !== []) {
                $groups[] = [
                    'date' => substr($release['published_at'], 0, 10),
                    'version' => $release['version'],
                    'entries' => $this->sortEntries($window),
                ];
            }
        }

        $unreleased = array_values(array_filter($entries, fn (array $entry): bool => ! isset($claimed[$entry['number']])));

        return array_merge($this->byDate($unreleased, null), $groups);
    }

    /**
     * @param  list<array{number:int,title:string,type:string,scope:?string,summary:string,merged_at:string,url:string}>  $entries
     * @return list<array{date:string,version:?string,entries:list<array{number:int,title:string,type:string,scope:?string,summary:string,merged_at:string,url:string}>}>
     */
    private function byDate(array $entries, ?string $version): array
    {
        $grouped = [];
        foreach ($entries as $entry) {
            $grouped[substr($entry['merged_at'], 0, 10)][] = $entry;
        }
        krsort($grouped);
        $groups = [];
        foreach ($grouped as $date => $day) {
            $groups[] = [
                'date' => $date,
                'version' => $version,
                'entries' => $this->sortEntries($day),
            ];
        }

        return $groups;
    }

    /**
     * @param  list<array{number:int,title:string,type:string,scope:?string,summary:string,merged_at:string,url:string}>  $entries
     * @return list<array{number:int,title:string,type:string,scope:?string,summary:string,merged_at:string,url:string}>
     */
    private function sortEntries(array $entries): array
    {
        usort($entries, function (array $a, array $b): int {
            return [$b['merged_at'], $b['number']] <=> [$a['merged_at'], $a['number']];
        });

        return $entries;
    }

    /**
     * @param  list<mixed>  $releases
     * @return list<array{version:string,published_at:string,previous:?string}>
     */
    private function releaseWindows(array $releases): array
    {
        $windows = [];
        foreach ($releases as $release) {
            if (! is_array($release) || ! empty($release['draft']) || ! empty($release['prerelease'])) {
                continue;
            }
            $published = $this->instant((string) ($release['published_at'] ?? $release['publishedAt'] ?? ''));
            $version = $this->version((string) ($release['tag'] ?? $release['tag_name'] ?? $release['version'] ?? ''));
            if ($published === null || $version === null) {
                continue;
            }
            $windows[] = ['version' => $version, 'published_at' => $published];
        }
        usort($windows, fn (array $a, array $b): int => $a['published_at'] <=> $b['published_at']);
        $previous = null;
        foreach ($windows as $index => $window) {
            $windows[$index]['previous'] = $previous;
            $previous = $window['published_at'];
        }

        return $windows;
    }

    private function version(string $tag): ?string
    {
        $tag = preg_replace('/^v(?=\d)/', '', trim($tag)) ?? '';
        if ($tag === '' || preg_match('/^[A-Za-z0-9._-]{1,40}$/', $tag) !== 1) {
            return null;
        }

        return $tag;
    }

    private function instant(string $value): ?string
    {
        $value = trim($value);
        if (preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/', $value) !== 1) {
            return null;
        }
        $parsed = DateTimeImmutable::createFromFormat('Y-m-d\TH:i:s\Z', $value, new DateTimeZone('UTC'));
        if ($parsed === false || $parsed->format('Y-m-d\TH:i:s\Z') !== $value) {
            return null;
        }

        return $value;
    }
}
