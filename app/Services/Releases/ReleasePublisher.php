<?php

declare(strict_types=1);

namespace App\Services\Releases;

use App\Models\CommunityRelease;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

final class ReleasePublisher
{
    public function __construct(private ReleaseCatalog $catalog) {}

    public function publish(string $commit): ?CommunityRelease
    {
        $version = (string) config('releases.version');
        if (! ReleaseCatalog::validVersion($version) || ! ReleaseCatalog::validCommit($commit) || $commit !== config('releases.commit')) {
            throw new RuntimeException('Expected commit must match this build’s cached revision and version.');
        }
        $notes = $version === '0.0.0' ? null : $this->catalog->notes($version);
        $url = rtrim((string) config('app.url'), '/');
        $parts = parse_url($url);
        if (! filter_var($url, FILTER_VALIDATE_URL) || ! is_array($parts) || ($parts['scheme'] ?? '') !== 'https'
            || isset($parts['user']) || isset($parts['pass']) || isset($parts['query']) || isset($parts['fragment'])
            || ($parts['path'] ?? '') !== '') {
            throw new RuntimeException('Release checks require an HTTPS APP_URL origin without credentials, path, query or fragment.');
        }
        try {
            $health = Http::acceptJson()->withHeaders(['Cache-Control' => 'no-store, no-cache', 'Pragma' => 'no-cache'])
                ->connectTimeout(5)->timeout(15)->withoutRedirecting()->get($url.'/up/release', ['check' => bin2hex(random_bytes(16))]);
        } catch (Throwable) {
            throw new RuntimeException('The live release readiness check failed. Nothing was published.');
        }
        $cacheControl = array_map('trim', explode(',', strtolower($health->header('Cache-Control'))));
        if (! $health->successful() || ! preg_match('/^application\/json(?:\s*;|$)/i', $health->header('Content-Type'))
            || ! in_array('no-store', $cacheControl, true) || $health->json('version') !== $version
            || $health->json('commit') !== $commit || $health->json('database_ready') !== true) {
            throw new RuntimeException('The live site has not confirmed this exact build and database readiness. Nothing was published.');
        }

        if ($version === '0.0.0') {
            return null;
        }

        // The unique version constraint and Laravel's create-or-first race recovery
        // preserve the first publication, including its notes, commit and date.
        return CommunityRelease::firstOrCreate(['version' => $version], [
            'commit' => $commit, 'notes' => $notes, 'published_at' => now(),
        ]);
    }
}
