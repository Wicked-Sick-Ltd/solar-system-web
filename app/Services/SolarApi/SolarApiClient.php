<?php

declare(strict_types=1);

namespace App\Services\SolarApi;

use App\Jobs\RefreshSolarCache;
use App\Services\SolarApi\Data\ObjectDetail;
use App\Services\SolarApi\Data\ObjectSummary;
use App\Services\SolarApi\Data\Paginated;
use App\Services\SolarApi\Data\Position;
use App\Services\SolarApi\Data\Ring;
use App\Services\SolarApi\Data\SearchResult;
use App\Services\SolarApi\Data\SkyPosition;
use App\Services\SolarApi\Data\Source;
use App\Services\SolarApi\Data\Stats;
use App\Services\SolarApi\Exceptions\SolarApiException;
use App\Services\SolarApi\Exceptions\SolarApiUnavailableException;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * The single gateway to the Solar System DB REST API. One public method per
 * endpoint, each returning typed DTOs. Reads are cached aggressively with a
 * stale-while-revalidate strategy (the backend only changes nightly) and every
 * call degrades gracefully: a 404 returns null/empty, an unreachable backend
 * throws {@see SolarApiUnavailableException} for the calling page to catch.
 *
 * Nothing here knows the backend's URL — it all keys off config('services.solar').
 */
class SolarApiClient
{
    private string $baseUrl;

    private int $timeout;

    /** @var array<string,int> */
    private array $ttl;

    public function __construct()
    {
        $config = config('services.solar');
        $this->baseUrl = $config['base_url'];
        $this->timeout = (int) $config['timeout'];
        $this->ttl = $config['cache'];
    }

    // ------------------------------------------------------------------
    // Catalogue
    // ------------------------------------------------------------------

    /**
     * Filterable, cursor-paginated list of objects.
     *
     * @param  array<string,mixed>  $filters  type, parent, min/max_radius_km, neo, pha, named_only
     * @return Paginated<ObjectSummary>
     */
    public function objects(array $filters = [], int $limit = 24, int $offset = 0): Paginated
    {
        $limit = max(1, min($limit, 100));
        $offset = max(0, $offset);

        $query = $this->cleanFilters($filters) + [
            // Fetch one extra row to learn whether a next page exists.
            'limit' => min($limit + 1, 500),
            'offset' => $offset,
        ];

        $data = $this->cachedGet('/objects', $query, $this->ttl['catalog']) ?? [];
        $rows = array_values((array) ($data['results'] ?? []));

        return $this->paginate($rows, $limit, $offset, ObjectSummary::fromArray(...));
    }

    /** Full record for one object by id, name or designation. Null when not found. */
    public function object(string $idOrName): ?ObjectDetail
    {
        $data = $this->cachedGet('/objects/'.$this->encodePath($idOrName), [], $this->ttl['catalog']);

        // A real detail response always carries an id; anything else (a stray
        // list envelope, a malformed body) is treated as "not found".
        if (! is_array($data) || empty($data['id'])) {
            return null;
        }

        return ObjectDetail::fromArray($data);
    }

    /**
     * Moons of a planet or dwarf planet.
     *
     * @return list<ObjectSummary>
     */
    public function moons(string $planet): array
    {
        return $this->mapResults(
            $this->cachedGet('/planets/'.$this->encodePath($planet).'/moons', [], $this->ttl['reference']),
            ObjectSummary::fromArray(...),
        );
    }

    /**
     * Ring system of a planet or dwarf planet.
     *
     * @return list<Ring>
     */
    public function rings(string $planet): array
    {
        return $this->mapResults(
            $this->cachedGet('/planets/'.$this->encodePath($planet).'/rings', [], $this->ttl['reference']),
            Ring::fromArray(...),
        );
    }

    /**
     * The IAU dwarf planets, optionally including leading candidates.
     *
     * @return list<ObjectSummary>
     */
    public function dwarfPlanets(bool $includeCandidates = false): array
    {
        return $this->mapResults(
            $this->cachedGet('/dwarf-planets', ['include_candidates' => $includeCandidates ? 'true' : 'false'], $this->ttl['reference']),
            ObjectSummary::fromArray(...),
        );
    }

    /**
     * Near-Earth Objects, brightest first.
     *
     * @return list<ObjectSummary>
     */
    public function neos(int $limit = 50): array
    {
        return $this->mapResults(
            $this->cachedGet('/neos', ['limit' => max(1, min($limit, 1000))], $this->ttl['catalog']),
            ObjectSummary::fromArray(...),
        );
    }

    /**
     * Numbered periodic comets, shortest period first.
     *
     * @return list<ObjectSummary>
     */
    public function periodicComets(int $limit = 50): array
    {
        return $this->mapResults(
            $this->cachedGet('/comets/periodic', ['limit' => max(1, min($limit, 2000))], $this->ttl['catalog']),
            ObjectSummary::fromArray(...),
        );
    }

    /**
     * Trans-Neptunian objects and centaurs, by semi-major axis.
     *
     * @return list<ObjectSummary>
     */
    public function tnos(int $limit = 50): array
    {
        return $this->mapResults(
            $this->cachedGet('/tnos', ['limit' => max(1, min($limit, 2000))], $this->ttl['catalog']),
            ObjectSummary::fromArray(...),
        );
    }

    /**
     * Fuzzy text search across names, designations and discoverers.
     *
     * @return list<SearchResult>
     */
    public function search(string $query, int $limit = 20): array
    {
        $query = trim($query);
        if ($query === '') {
            return [];
        }

        return $this->mapResults(
            $this->cachedGet('/search', ['q' => $query, 'limit' => max(1, min($limit, 100))], $this->ttl['catalog']),
            SearchResult::fromArray(...),
        );
    }

    // ------------------------------------------------------------------
    // Positions
    // ------------------------------------------------------------------

    /** Heliocentric position for a date (ISO 8601). Null when no elements / not found. */
    public function position(string $idOrName, string $date): ?Position
    {
        $data = $this->cachedGet(
            '/positions/'.$this->encodePath($idOrName),
            ['date' => $date],
            $this->ttl['positions'],
        );

        return is_array($data) ? Position::fromArray($data) : null;
    }

    /**
     * Where an object appears in Earth's sky. With a lat/lon the response also
     * carries the observer's view (alt/az, rise/set). Null on 404 so the page
     * section simply doesn't render (e.g. before the backend deploys /sky).
     *
     * Times and coordinates are rounded so the short cache actually hits:
     * geocentric calls to the hour, observer calls to 5 minutes and 0.1°.
     */
    public function sky(string $idOrName, ?string $datetime = null, ?float $lat = null, ?float $lon = null): ?SkyPosition
    {
        $observer = $lat !== null && $lon !== null;
        $when = $datetime !== null
            ? CarbonImmutable::parse($datetime)->utc()
            : CarbonImmutable::now('UTC');
        $when = $observer
            ? $when->subMinutes($when->minute % 5)->startOfMinute()
            : $when->startOfHour();

        $query = ['date' => $when->toIso8601ZuluString()];
        if ($observer) {
            $query['lat'] = round((float) $lat, 1);
            $query['lon'] = round((float) $lon, 1);
        }

        $data = $this->cachedGet('/sky/'.$this->encodePath($idOrName), $query, $this->ttl['positions']);

        return is_array($data) ? SkyPosition::fromArray($data) : null;
    }

    /**
     * Positions for several bodies at one date, fetched concurrently. Cache
     * hits are served without a request; only the misses go out, in a single
     * HTTP pool. Used by the orrery, where a page needs ~10 positions at once.
     *
     * Unlike the single-object reads this never throws: the orrery plots
     * whatever it has, so a flaky body (or a whole failed pool) falls back to
     * its last cached value and otherwise to null, leaving the rest of the
     * batch — including fresh cache hits — intact.
     *
     * @param  list<string>  $ids
     * @return array<string,?Position> keyed by the id passed in
     */
    public function positionsBatch(array $ids, string $date): array
    {
        $ids = array_values(array_unique($ids));
        $out = [];
        $pending = [];

        foreach ($ids as $id) {
            $path = '/positions/'.$this->encodePath($id);
            $query = ['date' => $date];
            $key = $this->cacheKey($path, $query);
            $entry = Cache::get($key);
            $cached = is_array($entry) && array_key_exists('soft', $entry) ? $entry : null;

            if ($cached !== null && $cached['soft'] > time()) {
                $out[$id] = $cached['value'];
            } else {
                // Keep the soft-stale value (if any) as the fallback for a
                // failed refresh, rather than dropping the body from the plot.
                $pending[$id] = compact('path', 'query', 'key') + ['stale' => $cached['value'] ?? null];
            }
        }

        if ($pending !== []) {
            $responses = $this->poolPositions($pending);

            foreach ($pending as $id => $request) {
                try {
                    $value = $this->responseValue(
                        $responses[$id] ?? new ConnectionException('Solar API returned no response'),
                        $request['path'],
                    );
                } catch (SolarApiException) {
                    // Already logged. Don't cache the failure — the next read
                    // should retry rather than serve an error for a hard TTL.
                    $out[$id] = $request['stale'];

                    continue;
                }

                $this->putCached($request['key'], $value, $this->ttl['positions']);
                $out[$id] = $value;
            }
        }

        return array_map(
            static fn ($value) => is_array($value) ? Position::fromArray($value) : null,
            $out,
        );
    }

    /**
     * Issue the pooled position requests. A pool that blows up as a whole
     * yields no responses, which the caller then treats per body as a
     * connection failure.
     *
     * @param  array<string,array{path:string,query:array<string,mixed>,key:string,stale:mixed}>  $pending
     * @return array<string,Response|Throwable>
     */
    private function poolPositions(array $pending): array
    {
        try {
            return Http::pool(fn ($pool) => array_map(
                fn (string $id) => $this->configureRequest($pool->as($id))
                    ->get($pending[$id]['path'], $pending[$id]['query']),
                array_keys($pending),
            ));
        } catch (Throwable $e) {
            Log::warning('Solar API positions batch failed', ['error' => $e->getMessage()]);

            return [];
        }
    }

    // ------------------------------------------------------------------
    // Reference
    // ------------------------------------------------------------------

    public function stats(): ?Stats
    {
        $data = $this->cachedGet('/stats', [], $this->ttl['reference']);

        return is_array($data) ? Stats::fromArray($data) : null;
    }

    /**
     * Catalogue-wide provenance, aggregated by source.
     *
     * @return list<Source>
     */
    public function sources(): array
    {
        return $this->mapResults(
            $this->cachedGet('/sources', [], $this->ttl['reference']),
            Source::fromArray(...),
        );
    }

    /**
     * Cheap reachability probe for graceful degradation, cached briefly so it
     * costs at most one upstream call per health window per worker.
     */
    public function reachable(): bool
    {
        return (bool) Cache::remember('solar:health', $this->ttl['health'], function (): bool {
            try {
                return Http::baseUrl($this->baseUrl)
                    ->timeout(min(3, $this->timeout))
                    ->get('/stats')
                    ->successful();
            } catch (Throwable) {
                return false;
            }
        });
    }

    // ------------------------------------------------------------------
    // Caching internals (stale-while-revalidate)
    // ------------------------------------------------------------------

    /**
     * Serve a cached response, refreshing it in the background once it goes
     * soft-stale. A cold miss fetches synchronously. The hard TTL is a multiple
     * of the soft TTL so a struggling backend can't leave a cold cache.
     *
     * @param  array<string,mixed>  $query
     */
    private function cachedGet(string $path, array $query, int $ttl): mixed
    {
        $key = $this->cacheKey($path, $query);
        $entry = Cache::get($key);

        if (is_array($entry) && array_key_exists('soft', $entry)) {
            if ($entry['soft'] > time()) {
                return $entry['value'];                       // fresh
            }

            // Soft-stale: serve immediately, revalidate out of band.
            RefreshSolarCache::dispatch($path, $query, $key, $ttl);

            return $entry['value'];
        }

        return $this->refreshInto($path, $query, $key, $ttl); // cold miss
    }

    /**
     * Fetch fresh and store it. Public so {@see RefreshSolarCache} can call it.
     *
     * @param  array<string,mixed>  $query
     */
    public function refreshInto(string $path, array $query, string $key, int $ttl): mixed
    {
        $value = $this->request($path, $query);

        $this->putCached($key, $value, $ttl);

        return $value;
    }

    /**
     * Low-level GET. Returns the decoded body, or null for a 404. Throws
     * {@see SolarApiUnavailableException} when the host can't be reached and
     * {@see SolarApiException} for other non-2xx responses.
     *
     * @param  array<string,mixed>  $query
     * @return array<mixed>|null
     */
    private function request(string $path, array $query = []): ?array
    {
        try {
            $response = $this->configureRequest(Http::withOptions([]))->get($path, $query);
        } catch (ConnectionException $e) {
            return $this->responseValue($e, $path);
        }

        return $this->responseValue($response, $path);
    }

    private function configureRequest(PendingRequest $request): PendingRequest
    {
        return $request
            ->baseUrl($this->baseUrl)
            ->timeout($this->timeout)
            ->acceptJson()
            ->retry(1, 150, throw: false);
    }

    /** @return array<mixed>|null */
    private function responseValue(Response|Throwable $response, string $path): ?array
    {
        if ($response instanceof Throwable) {
            Log::warning('Solar API unreachable', ['path' => $path, 'error' => $response->getMessage()]);

            throw new SolarApiUnavailableException("Solar API unreachable: {$response->getMessage()}", previous: $response);
        }

        if ($response->status() === 404) {
            return null;
        }

        if ($response->failed()) {
            Log::warning('Solar API error response', ['path' => $path, 'status' => $response->status()]);

            throw new SolarApiException("Solar API returned {$response->status()} for {$path}");
        }

        $json = $response->json();

        return is_array($json) ? $json : null;
    }

    private function putCached(string $key, mixed $value, int $ttl): void
    {
        Cache::put($key, ['value' => $value, 'soft' => time() + $ttl], $ttl * 6);
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    /**
     * @param  list<array<string,mixed>>  $rows
     * @param  callable(array<string,mixed>): mixed  $map
     * @return Paginated<mixed>
     */
    private function paginate(array $rows, int $limit, int $offset, callable $map): Paginated
    {
        $hasMore = count($rows) > $limit;
        if ($hasMore) {
            $rows = array_slice($rows, 0, $limit);
        }

        return new Paginated(array_map($map, $rows), $limit, $offset, $hasMore);
    }

    /**
     * Map a `{results: [...]}` envelope into a list of DTOs.
     *
     * @return list<mixed>
     */
    private function mapResults(mixed $data, callable $map): array
    {
        $rows = is_array($data) ? (array) ($data['results'] ?? []) : [];

        return array_values(array_map($map, array_filter($rows, 'is_array')));
    }

    /**
     * Keep only the filter keys the backend understands, dropping null/blank.
     *
     * @param  array<string,mixed>  $filters
     * @return array<string,mixed>
     */
    private function cleanFilters(array $filters): array
    {
        $allowed = [
            'type', 'parent', 'min_radius_km', 'max_radius_km', 'max_eccentricity',
            'min_semi_major_axis_au', 'max_semi_major_axis_au', 'neo', 'pha', 'named_only',
        ];

        $clean = [];
        foreach ($allowed as $key) {
            $value = $filters[$key] ?? null;
            if ($value === null || $value === '' || $value === false) {
                continue;
            }
            // Booleans go to the API as lowercase strings.
            $clean[$key] = is_bool($value) ? 'true' : $value;
        }

        return $clean;
    }

    /** @param array<string,mixed> $query */
    private function cacheKey(string $path, array $query): string
    {
        ksort($query);

        return 'solar:'.sha1($path.'?'.http_build_query($query));
    }

    /** Encode a name/id for a path segment while keeping it readable in logs. */
    private function encodePath(string $value): string
    {
        return implode('/', array_map('rawurlencode', explode('/', trim($value, '/'))));
    }
}
