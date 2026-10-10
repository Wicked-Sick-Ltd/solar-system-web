<?php

declare(strict_types=1);

namespace App\Services\Changelog;

use Illuminate\Support\Facades\Http;
use RuntimeException;
use Symfony\Component\Process\Process;
use Throwable;

/**
 * Reads merged pull requests and published releases. The credential stays in
 * the request and is never written to the notes file or the console.
 */
final class GitHubMergedPullRequests
{
    /**
     * @return array{pulls:list<array{number:int,title:string,body:string,merged_at:string}>,releases:list<array{tag:string,published_at:string,draft:bool,prerelease:bool}>}
     */
    public function fetch(string $repository): array
    {
        if (preg_match('/^[A-Za-z0-9][A-Za-z0-9_.-]*\/[A-Za-z0-9][A-Za-z0-9_.-]*$/', $repository) !== 1 || str_contains($repository, '..')) {
            throw new RuntimeException('The repository must be an owner/name.');
        }
        $token = $this->token();
        if ($token === null) {
            throw new RuntimeException('Set GITHUB_TOKEN or GH_TOKEN to read merged pull requests. The token is not stored.');
        }

        try {
            return [
                'pulls' => $this->pulls($repository, $token),
                'releases' => $this->releases($repository, $token),
            ];
        } finally {
            unset($token);
        }
    }

    /**
     * @return list<array{number:int,title:string,body:string,merged_at:string}>
     */
    private function pulls(string $repository, string $token): array
    {
        $pulls = [];
        for ($page = 1; $page <= 10; $page++) {
            $batch = $this->get($repository, $token, 'pulls', [
                'state' => 'closed',
                'per_page' => 100,
                'page' => $page,
                'sort' => 'updated',
                'direction' => 'desc',
            ]);
            foreach ($batch as $pull) {
                if (! is_array($pull) || ! is_string($pull['merged_at'] ?? null) || $pull['merged_at'] === '') {
                    continue;
                }
                $pulls[] = [
                    'number' => (int) ($pull['number'] ?? 0),
                    'title' => (string) ($pull['title'] ?? ''),
                    'body' => (string) ($pull['body'] ?? ''),
                    'merged_at' => $pull['merged_at'],
                ];
            }
            if (count($batch) < 100) {
                break;
            }
        }

        return $pulls;
    }

    /**
     * @return list<array{tag:string,published_at:string,draft:bool,prerelease:bool}>
     */
    private function releases(string $repository, string $token): array
    {
        $releases = [];
        foreach ($this->get($repository, $token, 'releases', ['per_page' => 100]) as $release) {
            if (! is_array($release) || ! is_string($release['published_at'] ?? null)) {
                continue;
            }
            $releases[] = [
                'tag' => (string) ($release['tag_name'] ?? ''),
                'published_at' => $release['published_at'],
                'draft' => (bool) ($release['draft'] ?? false),
                'prerelease' => (bool) ($release['prerelease'] ?? false),
            ];
        }

        return $releases;
    }

    /**
     * @param  array<string, int|string>  $query
     * @return list<mixed>
     */
    private function get(string $repository, string $token, string $resource, array $query): array
    {
        $response = Http::withToken($token)
            ->withHeaders([
                'Accept' => 'application/vnd.github+json',
                'X-GitHub-Api-Version' => '2022-11-28',
                'User-Agent' => 'PublicUniverse-Changelog',
            ])
            ->withOptions(['allow_redirects' => false])
            ->timeout(20)
            ->get('https://api.github.com/repos/'.$repository.'/'.$resource, $query);

        if (! $response->successful()) {
            throw new RuntimeException('GitHub did not return merged pull requests.');
        }
        $payload = $response->json();
        if (! is_array($payload) || array_is_list($payload) === false) {
            throw new RuntimeException('GitHub did not return merged pull requests.');
        }

        return $payload;
    }

    private function token(): ?string
    {
        foreach (['GITHUB_TOKEN', 'GH_TOKEN'] as $name) {
            $value = getenv($name);
            if (is_string($value) && $value !== '') {
                return $value;
            }
        }
        if (app()->runningUnitTests()) {
            return null;
        }

        try {
            $process = new Process(['gh', 'auth', 'token']);
            $process->setTimeout(15);
            $process->run();
        } catch (Throwable) {
            return null;
        }
        if (! $process->isSuccessful()) {
            return null;
        }
        $value = trim($process->getOutput());

        return $value === '' ? null : $value;
    }
}
