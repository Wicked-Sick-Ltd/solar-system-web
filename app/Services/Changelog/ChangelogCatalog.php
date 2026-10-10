<?php

declare(strict_types=1);

namespace App\Services\Changelog;

use JsonException;
use RuntimeException;

/** Reads the committed release notes. A missing file is an empty history, not an error. */
final class ChangelogCatalog
{
    /**
     * @return list<array{date:string,version:?string,entries:list<array{number:int,title:string,type:string,scope:?string,summary:string,merged_at:string,url:string}>}>
     */
    public function groups(): array
    {
        $document = $this->document();

        return $document['groups'] ?? [];
    }

    /**
     * @return list<array{number:int,title:string,type:string,scope:?string,summary:string,merged_at:string,url:string}>
     */
    public function entries(): array
    {
        $entries = [];
        foreach ($this->groups() as $group) {
            foreach ($group['entries'] as $entry) {
                $entries[] = $entry;
            }
        }

        return $entries;
    }

    public function generatedAt(): ?string
    {
        return $this->document()['generated_at'] ?? null;
    }

    /**
     * @return array{repository:string,generated_at:string,groups:list<array{date:string,version:?string,entries:list<array{number:int,title:string,type:string,scope:?string,summary:string,merged_at:string,url:string}>}>}|array{}
     */
    private function document(): array
    {
        $path = (string) config('changelog.path');
        if ($path === '' || ! is_file($path)) {
            return [];
        }
        if (filesize($path) > 512_000) {
            throw new RuntimeException('Release notes could not be read.');
        }

        try {
            $data = json_decode(file_get_contents($path) ?: '', true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new RuntimeException('Release notes could not be read.');
        }
        if (! is_array($data)) {
            throw new RuntimeException('Release notes could not be read.');
        }

        return $this->validated($data);
    }

    /**
     * @param  array<mixed>  $data
     * @return array{repository:string,generated_at:string,groups:list<array{date:string,version:?string,entries:list<array{number:int,title:string,type:string,scope:?string,summary:string,merged_at:string,url:string}>}>}
     */
    private function validated(array $data): array
    {
        $repository = $data['repository'] ?? null;
        $generatedAt = $data['generated_at'] ?? null;
        $groups = $data['groups'] ?? null;
        if (! is_string($repository) || preg_match('/^[A-Za-z0-9][A-Za-z0-9_.-]*\/[A-Za-z0-9][A-Za-z0-9_.-]*$/', $repository) !== 1 || str_contains($repository, '..')
            || ! is_string($generatedAt) || preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/', $generatedAt) !== 1
            || ! is_array($groups) || array_is_list($groups) === false) {
            throw new RuntimeException('Release notes could not be read.');
        }

        $validated = [];
        foreach ($groups as $group) {
            if (! is_array($group)) {
                throw new RuntimeException('Release notes could not be read.');
            }
            $date = $group['date'] ?? null;
            $version = $group['version'] ?? null;
            $entries = $group['entries'] ?? null;
            if (! is_string($date) || preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) !== 1
                || (! is_null($version) && (! is_string($version) || preg_match('/^[A-Za-z0-9._-]{1,40}$/', $version) !== 1))
                || ! is_array($entries) || array_is_list($entries) === false) {
                throw new RuntimeException('Release notes could not be read.');
            }
            $validatedEntries = [];
            foreach ($entries as $entry) {
                $validatedEntries[] = $this->entry($repository, $entry);
            }
            $validated[] = ['date' => $date, 'version' => $version, 'entries' => $validatedEntries];
        }

        return ['repository' => $repository, 'generated_at' => $generatedAt, 'groups' => $validated];
    }

    /**
     * @return array{number:int,title:string,type:string,scope:?string,summary:string,merged_at:string,url:string}
     */
    private function entry(string $repository, mixed $entry): array
    {
        if (! is_array($entry)) {
            throw new RuntimeException('Release notes could not be read.');
        }
        $number = $entry['number'] ?? null;
        $title = $entry['title'] ?? null;
        $type = $entry['type'] ?? null;
        $scope = $entry['scope'] ?? null;
        $summary = $entry['summary'] ?? null;
        $mergedAt = $entry['merged_at'] ?? null;
        $url = $entry['url'] ?? null;
        $valid = is_int($number) && $number > 0
            && is_string($title) && $title !== '' && mb_strlen($title) <= 300
            && is_string($type) && preg_match('/^[a-z]{1,20}$/', $type) === 1
            && (is_null($scope) || (is_string($scope) && $scope !== '' && mb_strlen($scope) <= 80))
            && is_string($summary) && $summary !== '' && mb_strlen($summary) <= 400
            && is_string($mergedAt) && preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/', $mergedAt) === 1
            && $url === 'https://github.com/'.$repository.'/pull/'.$number;
        if (! $valid) {
            throw new RuntimeException('Release notes could not be read.');
        }

        return [
            'number' => $number,
            'title' => $title,
            'type' => $type,
            'scope' => $scope,
            'summary' => $summary,
            'merged_at' => $mergedAt,
            'url' => $url,
        ];
    }
}
