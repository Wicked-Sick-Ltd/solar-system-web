<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Services\SolarApi\SolarApiClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

/**
 * Stale-while-revalidate worker. When a cached catalogue/reference entry has
 * gone soft-stale, the request serves the stale copy immediately and dispatches
 * this job to refresh it out-of-band, so the cache never goes cold in
 * production. With the sync queue driver (local dev) it simply runs inline.
 *
 * Everything it needs is plain serialisable data — path, query, key, ttl — so
 * no closures are captured.
 */
final class RefreshSolarCache implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    /** Keep one refresh per cache key queued or running for up to 15 minutes. */
    public int $uniqueFor = 900;

    /** Must remain below the queue connection's retry_after (90s by default). */
    public int $timeout = 60;

    /** @param array<string,mixed> $query */
    public function __construct(
        public string $path,
        public array $query,
        public string $cacheKey,
        public int $ttl,
    ) {}

    public function uniqueId(): string
    {
        return $this->cacheKey;
    }

    public function handle(SolarApiClient $client): void
    {
        try {
            $client->refreshInto($this->path, $this->query, $this->cacheKey, $this->ttl);
        } catch (Throwable) {
            // A failed background refresh is non-fatal: the existing stale entry
            // stays in place and we try again on the next stale read.
        }
    }
}
