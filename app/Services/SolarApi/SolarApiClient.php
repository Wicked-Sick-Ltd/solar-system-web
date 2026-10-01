<?php

declare(strict_types=1);

namespace App\Services\SolarApi;

use App\Jobs\RefreshSolarCache;
use App\Services\SolarApi\Data\CloseApproach;
use App\Services\SolarApi\Data\Exoplanet;
use App\Services\SolarApi\Data\ExoplanetHost;
use App\Services\SolarApi\Data\GalaxyMap;
use App\Services\SolarApi\Data\MeteorCatalogue;
use App\Services\SolarApi\Data\MeteorShower;
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

    public function meteorShowers(bool $establishedOnly = false, ?string $activeOn = null): MeteorCatalogue
    {
        $query = ['established_only' => $establishedOnly ? 'true' : 'false', 'limit' => MeteorCatalogue::LIMIT];
        if ($activeOn !== null && $activeOn !== '') {
            $query['active_on'] = $activeOn;
        }
        $data = $this->cachedGet('/meteor-showers', $query, $this->ttl['catalog']);
        if (! is_array($data) || ! isset($data['items'], $data['count']) || ! is_array($data['items'])
            || ! array_is_list($data['items']) || ! is_int($data['count']) || $data['count'] !== count($data['items'])) {
            throw new SolarApiException('Meteor shower catalogue is unavailable on this backend.');
        }

        return MeteorCatalogue::fromRows($this->validatedMeteorRows($data['items']));
    }

    public function meteorShower(string $code): ?MeteorShower
    {
        $data = $this->cachedGet('/meteor-showers/'.rawurlencode($code), [], $this->ttl['catalog']);
        if ($data === null) {
            return null;
        }
        if (! is_int($data['iau_no'] ?? null) || $data['iau_no'] < 0
            || ! is_string($data['code'] ?? null) || trim($data['code']) === ''
            || ! is_string($data['name'] ?? null) || trim($data['name']) === ''
            || ! is_array($data['parameter_sets'] ?? null) || ! array_is_list($data['parameter_sets'])) {
            throw new SolarApiException('Meteor shower detail is unavailable on this backend.');
        }

        $data['parameter_sets'] = $this->validatedMeteorRows($data['parameter_sets']);
        foreach ($data['parameter_sets'] as $set) {
            if ($set['iau_no'] !== $data['iau_no']) {
                throw new SolarApiException('Meteor shower parameter sets have inconsistent identities.');
            }
        }

        return MeteorShower::fromArray($data);
    }

    /**
     * Validate the REST boundary before typed DTO construction. Never drop a
     * malformed campaign silently or let an upstream shape error crash a page.
     *
     * @param  list<mixed>  $rows
     * @return list<array<string,mixed>>
     */
    private function validatedMeteorRows(array $rows): array
    {
        foreach ($rows as $row) {
            if (! is_array($row)) {
                throw new SolarApiException('Malformed meteor shower parameter set.');
            }
            foreach (['iau_no', 'ad_no'] as $field) {
                if (! is_int($row[$field] ?? null) || $row[$field] < 0) {
                    throw new SolarApiException('Malformed meteor shower identifier.');
                }
            }
            foreach (['code', 'name'] as $field) {
                if (! is_string($row[$field] ?? null) || trim($row[$field]) === '') {
                    throw new SolarApiException('Malformed meteor shower name or code.');
                }
            }
            foreach (['status_label', 'activity', 'parent_body', 'parent_object_id', 'shower_group',
                'technique', 'reference', 'submitted_on', 'source'] as $field) {
                if (isset($row[$field]) && ! is_string($row[$field])) {
                    throw new SolarApiException('Malformed meteor shower text field.');
                }
            }
            foreach (['status_code', 'n_members'] as $field) {
                if (isset($row[$field]) && ! is_int($row[$field])) {
                    throw new SolarApiException('Malformed meteor shower integer field.');
                }
            }
            foreach (['solar_longitude_deg', 'ra_deg', 'dec_deg', 'dra_deg_per_day', 'ddec_deg_per_day',
                'vg_km_s', 'a_au', 'q_au', 'e', 'peri_deg', 'node_deg', 'incl_deg'] as $field) {
                $value = $row[$field] ?? null;
                if ($value !== null && ((! is_int($value) && ! is_float($value)) || ! is_finite($value))) {
                    throw new SolarApiException('Malformed meteor shower measurement.');
                }
            }
        }

        return $rows;
    }

    /** @param array<string,mixed> $filters
     * @return Paginated<Exoplanet>
     */
    public function exoplanets(array $filters = [], int $limit = 24, int $offset = 0): Paginated
    {
        $limit = max(1, min(100, $limit));
        $offset = max(0, min(100000, $offset));
        $filters = array_filter(array_intersect_key($filters, array_flip(['q', 'discovery_method', 'max_distance_pc'])), static fn ($value) => $value !== null && $value !== '');
        $data = $this->cachedGet('/exoplanets', $filters + ['limit' => $limit + 1, 'offset' => $offset], $this->ttl['catalog']);
        if (! is_array($data) || ($data['available'] ?? null) !== true) {
            throw new SolarApiException('Exoplanet catalogue is not available yet.');
        }

        $rows = $data['results'] ?? null;
        if (! is_array($rows) || ! array_is_list($rows)) {
            throw new SolarApiException('Exoplanet catalogue returned an invalid result envelope.');
        }

        foreach ($rows as $row) {
            if (! is_array($row)) {
                throw new SolarApiException('Exoplanet catalogue returned an invalid record.');
            }
            foreach (['id', 'name', 'host_id'] as $field) {
                if (! isset($row[$field]) || ! is_string($row[$field]) || trim($row[$field]) === '') {
                    throw new SolarApiException('Exoplanet catalogue returned an invalid identity.');
                }
            }
            // Optional scientific values may be absent or null in older data.
            // Validate text before DTO casting so malformed values cannot turn
            // one catalogue failure into a page-level PHP error.
            foreach (['host_name', 'discovery_method', 'mass_provenance', 'retrieved_at'] as $field) {
                if (isset($row[$field]) && ! is_string($row[$field])) {
                    throw new SolarApiException('Exoplanet catalogue returned invalid text metadata.');
                }
            }
            if (isset($row['source_data']) && ! is_array($row['source_data'])) {
                throw new SolarApiException('Exoplanet catalogue returned invalid measurements.');
            }
        }

        return $this->paginate($rows, $limit, $offset, Exoplanet::fromArray(...));
    }

    public function exoplanet(string $id): ?Exoplanet
    {
        $d = $this->cachedGet('/exoplanets/'.rawurlencode($id), [], $this->ttl['catalog']);

        return is_array($d) && isset($d['id']) ? Exoplanet::fromArray($d) : null;
    }

    public function exoplanetHost(string $id): ?ExoplanetHost
    {
        $d = $this->cachedGet('/exoplanet-hosts/'.rawurlencode($id), [], $this->ttl['catalog']);

        return is_array($d) && isset($d['id']) ? ExoplanetHost::fromArray($d) : null;
    }

    public function galaxyMap(): GalaxyMap
    {
        $d = $this->cachedGet('/galaxy', [], $this->ttl['catalog']);
        if (! is_array($d) || ($d['available'] ?? null) !== true
            || ! is_array($d['results'] ?? null) || ! array_is_list($d['results'])
            || count($d['results']) > 10000) {
            throw new SolarApiException('Galaxy map is not available yet.');
        }
        if ((isset($d['unmapped_hosts']) && (! is_int($d['unmapped_hosts']) || $d['unmapped_hosts'] < 0))
            || (isset($d['truncated']) && ! is_bool($d['truncated']))) {
            throw new SolarApiException('Invalid galaxy coverage metadata.');
        }
        $ids = [];
        foreach ($d['results'] as $host) {
            if (! is_array($host) || ! is_string($host['id'] ?? null) || trim($host['id']) === ''
                || ! is_string($host['name'] ?? null) || trim($host['name']) === ''
                || isset($ids[$host['id']]) || ! is_int($host['planet_count'] ?? null) || $host['planet_count'] < 0) {
                throw new SolarApiException('Invalid galaxy host identity.');
            }
            $ids[$host['id']] = true;
            foreach (['distance_pc', 'x_pc', 'y_pc', 'z_pc', 'galactocentric_x_pc', 'galactocentric_y_pc', 'galactocentric_z_pc'] as $field) {
                $value = $host[$field] ?? null;
                if ((! is_float($value) && ! is_int($value)) || ! is_finite((float) $value)
                    || ($field === 'distance_pc' && $value <= 0)) {
                    throw new SolarApiException('Invalid galaxy host measurement.');
                }
            }
            foreach (['distance_error_plus_pc', 'distance_error_minus_pc'] as $field) {
                $value = $host[$field] ?? null;
                if ($value !== null && ((! is_float($value) && ! is_int($value)) || ! is_finite((float) $value))) {
                    throw new SolarApiException('Invalid galaxy distance uncertainty.');
                }
            }
        }

        return GalaxyMap::fromArray($d);
    }

    /**
     * Filterable, cursor-paginated list of objects.
     *
     * @param  array<string,mixed>  $filters  type, parent, min/max_radius_km, neo, pha, named_only
     * @return Paginated<ObjectSummary>
     */
    public function objects(array $filters = [], int $limit = 24, int $offset = 0, ?string $after = null): Paginated
    {
        $limit = max(1, min($limit, 100));
        $offset = max(0, $offset);

        $query = $this->cleanFilters($filters) + [
            // Fetch one extra row to learn whether a next page exists.
            'limit' => min($limit + 1, 500),
            'offset' => $offset,
        ];

        if ($after !== null) {
            $query['after'] = $after;
            $query['offset'] = 0;
        }

        $data = $this->cachedGet('/objects', $query, $this->ttl['catalog']);
        if (! is_array($data) || ! isset($data['results']) || ! is_array($data['results'])
            || ($after !== null && ! array_key_exists('next_after', $data))) {
            throw new SolarApiException('Object catalogue is not available in this browsing mode.');
        }
        $rows = array_values($data['results']);

        $page = $this->paginate($rows, $limit, $after !== null ? 0 : $offset, ObjectSummary::fromArray(...));
        // The API cursor points to its last returned (overfetched) row. Use our
        // last displayed row instead, or the next page would skip one object.
        $last = $page->items[count($page->items) - 1] ?? null;

        return new Paginated($page->items, $page->limit, $page->offset, $page->hasMore,
            $after !== null && $page->hasMore ? $last?->id : null);
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
     * Every close approach to Earth between two ISO dates within $maxDistAu,
     * nearest first (the backend's order).
     *
     * @return list<CloseApproach>
     */
    public function closeApproaches(string $from, string $to, float $maxDistAu = 0.05, int $limit = 200): array
    {
        $data = $this->cachedGet('/close-approaches', [
            'from' => $from, 'to' => $to, 'body' => 'Earth',
            'max_dist_au' => $maxDistAu, 'limit' => max(1, min($limit, 1000)),
        ], $this->ttl['catalog']);
        if (! is_array($data) || ! is_array($data['results'] ?? null) || ! array_is_list($data['results'])) {
            throw new SolarApiException('Close-approach catalogue is unavailable.');
        }
        foreach ($data['results'] as $row) {
            if (! is_array($row)) {
                throw new SolarApiException('Invalid close-approach record.');
            }
            foreach (['object_id', 'body', 'cd_iso'] as $field) {
                if (! is_string($row[$field] ?? null) || trim($row[$field]) === '') {
                    throw new SolarApiException('Invalid close-approach identity or date.');
                }
            }
            if (strcasecmp($row['body'], 'Earth') !== 0) {
                throw new SolarApiException('Close-approach response is for another body.');
            }
            foreach (['name', 'designation', 't_sigma'] as $field) {
                if (isset($row[$field]) && ! is_string($row[$field])) {
                    throw new SolarApiException('Invalid close-approach label.');
                }
            }
            if (trim($row['name'] ?? '') === '' && trim($row['designation'] ?? '') === '') {
                throw new SolarApiException('Missing close-approach object label.');
            }
            // The catalogue exposes UTC timestamps at second precision. Reject
            // impossible dates rather than sorting an invented or missing time.
            try {
                $date = CarbonImmutable::createFromFormat('!Y-m-d\TH:i:s\Z', $row['cd_iso'], 'UTC');
            } catch (Throwable) {
                $date = null;
            }
            if (! $date || $date->format('Y-m-d\TH:i:s\Z') !== $row['cd_iso'] || $date->year < 1) {
                throw new SolarApiException('Invalid close-approach date.');
            }
            foreach (['dist_au', 'dist_min_au', 'dist_max_au', 'v_rel_km_s'] as $field) {
                $value = $row[$field] ?? null;
                if ($value !== null && (! is_numeric($value) || ! is_finite((float) $value) || (float) $value < 0)) {
                    throw new SolarApiException('Invalid close-approach measurement.');
                }
            }
        }

        return array_map(static function (array $row): CloseApproach {
            // Treat a blank display name as absent so designation fallback works.
            if (trim($row['name'] ?? '') === '') {
                $row['name'] = null;
            }

            return CloseApproach::fromArray($row);
        }, $data['results']);
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

        $data = $this->cachedGet('/search', ['q' => $query, 'limit' => max(1, min($limit, 100))], $this->ttl['catalog']);
        if (! is_array($data) || ! isset($data['results']) || ! is_array($data['results'])) {
            throw new SolarApiException('Solar-system search is not available.');
        }

        return $this->mapResults($data, SearchResult::fromArray(...));
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
     * geocentric calls to the hour, observer calls to 5 minutes and 0.01°.
     */
    public function sky(string $idOrName, ?string $datetime = null, ?float $lat = null, ?float $lon = null): ?SkyPosition
    {
        if (($lat === null) !== ($lon === null)
            || ($lat !== null && (! is_finite($lat) || abs($lat) > 90))
            || ($lon !== null && (! is_finite($lon) || abs($lon) > 180))) {
            throw new SolarApiException('Invalid observer coordinates.');
        }
        $observer = $lat !== null && $lon !== null;
        $when = $datetime !== null
            ? CarbonImmutable::parse($datetime)->utc()
            : CarbonImmutable::now('UTC');
        $when = $observer
            ? $when->subMinutes($when->minute % 5)->startOfMinute()
            : $when->startOfHour();

        $query = ['date' => $when->toIso8601ZuluString()];
        if ($observer) {
            $query['lat'] = round((float) $lat, 2);
            $query['lon'] = round((float) $lon, 2);
        }

        $data = $this->cachedGet('/sky/'.$this->encodePath($idOrName), $query, $this->ttl['positions']);

        if ($data === null) {
            return null;
        }
        if (! is_array($data) || ! is_string($data['name'] ?? null) || trim($data['name']) === '') {
            throw new SolarApiException('Invalid sky-position response.');
        }
        foreach (['input_datetime', 'resolved_from', 'ra_hms', 'dec_dms', 'hemisphere', 'visible_from', 'accuracy_note'] as $field) {
            if (isset($data[$field]) && ! is_string($data[$field])) {
                throw new SolarApiException('Invalid sky-position metadata.');
            }
        }
        foreach (['ra_deg' => [0, 360], 'dec_deg' => [-90, 90], 'elongation_deg' => [0, 180],
            'distance_from_earth_au' => [0, PHP_FLOAT_MAX], 'distance_from_sun_au' => [0, PHP_FLOAT_MAX]] as $field => [$min, $max]) {
            $value = $data[$field] ?? null;
            if ($value !== null && (! is_numeric($value) || ! is_finite((float) $value) || (float) $value < $min || (float) $value > $max)) {
                throw new SolarApiException('Invalid sky-position measurement.');
            }
        }
        if (isset($data['constellation'])) {
            if (! is_array($data['constellation'])) {
                throw new SolarApiException('Invalid constellation metadata.');
            }
            foreach (['name', 'abbr'] as $field) {
                if (isset($data['constellation'][$field]) && ! is_string($data['constellation'][$field])) {
                    throw new SolarApiException('Invalid constellation label.');
                }
            }
        }
        $view = $data['observer'] ?? null;
        if (($observer && ! is_array($view)) || ($view !== null && ! is_array($view))) {
            throw new SolarApiException('Observer calculation unavailable.');
        }
        if (is_array($view)) {
            foreach (['lat' => [-90, 90], 'lon' => [-180, 180], 'altitude_deg' => [-90, 90],
                'azimuth_deg' => [0, 360], 'sun_altitude_deg' => [-90, 90]] as $field => [$min, $max]) {
                $value = $view[$field] ?? null;
                if (! is_numeric($value) || ! is_finite((float) $value) || (float) $value < $min || (float) $value > $max) {
                    throw new SolarApiException('Invalid observer measurement.');
                }
            }
            foreach (['is_up', 'is_dark', 'circumpolar', 'never_rises'] as $field) {
                if (! is_bool($view[$field] ?? null)) {
                    throw new SolarApiException('Invalid observer status.');
                }
            }
            foreach (['rise_utc', 'transit_utc', 'set_utc'] as $field) {
                $value = $view[$field] ?? null;
                if ($value === null) {
                    continue;
                }
                try {
                    $date = is_string($value) ? CarbonImmutable::createFromFormat('!Y-m-d\TH:i:s\Z', $value, 'UTC') : null;
                } catch (Throwable) {
                    $date = null;
                }
                if (! $date || $date->year < 1 || $date->format('Y-m-d\TH:i:s\Z') !== $value) {
                    throw new SolarApiException('Invalid observer event time.');
                }
            }
        }

        return SkyPosition::fromArray($data);
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

        return array_map(static function ($value): ?Position {
            if (! is_array($value)) {
                return null;
            }
            // A malformed metadata field must omit one body, not fail the whole
            // drawing with an array-to-string conversion in the tolerant DTO.
            foreach (['name', 'designation', 'input_date', 'frame', 'accuracy_note'] as $field) {
                if (isset($value[$field]) && ! is_scalar($value[$field])) {
                    return null;
                }
            }

            return Position::fromArray($value);
        }, $out);
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
            'orbit_class', 'max_moid_au', 'min_diameter_km', 'max_condition_code', 'discovered_after',
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
