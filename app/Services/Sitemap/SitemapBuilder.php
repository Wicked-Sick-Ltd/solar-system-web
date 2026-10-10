<?php

declare(strict_types=1);

namespace App\Services\Sitemap;

use App\Services\Releases\ReleaseCatalog;
use App\Services\SolarApi\CatalogueContext;
use App\Services\SolarApi\Data\CatalogueIdentity;
use App\Services\SolarApi\Data\Exoplanet;
use App\Services\SolarApi\Data\ObjectDetail;
use App\Services\SolarApi\Exceptions\SolarApiException;
use App\Services\SolarApi\Exceptions\SolarApiUnavailableException;
use App\Services\SolarApi\SolarApiClient;
use App\Support\ObjectOfTheDay;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Builds the sitemap index and one urlset per kind of page.
 *
 * Solar-system objects stay the named, browsable set (planets, moons, dwarf
 * planets, and the brightest comets, near-Earth objects and TNOs). Exoplanets
 * and their host systems are the full catalogue the detail routes can render.
 * lastmod is the catalogue build time or the record's own timestamp, never the
 * clock of the request that happens to render the file.
 */
final class SitemapBuilder
{
    public const CACHE_KEY = 'sitemap.documents';

    /** @var list<string> */
    private const PAGES = [
        'home', 'explore', 'observe', 'learn', 'objects.index', 'planets.index', 'dwarf-planets',
        'asteroids', 'comets', 'tnos', 'close-approaches', 'orrery', 'exoplanets.index', 'systems.index',
        'galaxy', 'meteor-showers.index', 'releases.index', 'about', 'educators', 'higher-education',
        'plugin', 'feedback', 'api', 'privacy',
    ];

    public function __construct(private SolarApiClient $api) {}

    /**
     * @return array{index:string, children:array<string, string>}
     */
    public function remember(): array
    {
        $cached = Cache::get(self::CACHE_KEY);
        if (is_array($cached) && is_string($cached['index'] ?? null) && is_array($cached['children'] ?? null) && $cached['children'] !== []) {
            /** @var array{index:string, children:array<string, string>} $cached */
            return ['index' => $cached['index'], 'children' => $cached['children']];
        }

        $built = $this->build();
        $payload = ['index' => $built['index'], 'children' => $built['children']];
        Cache::put(self::CACHE_KEY, $payload, $built['complete'] ? now()->addDay() : now()->addMinutes(10));

        return $payload;
    }

    /**
     * @return array{index:string, children:array<string, string>, complete:bool}
     */
    public function build(): array
    {
        $complete = true;
        $catalogue = $this->catalogueLastmod();
        $sections = [
            'pages' => $this->pages($catalogue),
            'objects' => [],
            'exoplanets' => [],
            'systems' => [],
            'releases' => [],
            'today' => $this->today($catalogue),
        ];

        try {
            $sections['objects'] = $this->objects($catalogue);
        } catch (SolarApiException) {
            $complete = false;
        }
        try {
            [$sections['exoplanets'], $sections['systems']] = $this->exoplanetSections($catalogue);
        } catch (SolarApiException) {
            $complete = false;
        }
        $sections['releases'] = $this->releases();

        /** @var array<string, string> $children */
        $children = [];
        /** @var list<array{loc:string, lastmod:?string}> $index */
        $index = [];

        foreach ($sections as $name => $entries) {
            $chunks = SitemapXml::chunks($entries);
            foreach ($chunks as $part => $chunk) {
                $child = count($chunks) === 1 ? $name : $name.'-'.($part + 1);
                $loc = IndexableUrl::forRoute('sitemap.child', ['name' => $child]);
                if ($loc === null) {
                    continue;
                }
                $children[$child] = SitemapXml::urlset($chunk);
                $index[] = ['loc' => $loc, 'lastmod' => $this->latest($chunk)];
            }
        }

        return [
            'index' => SitemapXml::index($index),
            'children' => $children,
            'complete' => $complete && $children !== [],
        ];
    }

    /**
     * @return list<array{loc:string, lastmod:?string, priority:?string}>
     */
    private function pages(?string $lastmod): array
    {
        $entries = [];
        foreach (self::PAGES as $name) {
            $priority = $name === 'home' ? '1.0' : '0.7';
            $entry = $this->entry(IndexableUrl::forRoute($name), $lastmod, $priority);
            if ($entry !== null) {
                $entries[] = $entry;
            }
        }

        return $entries;
    }

    /**
     * @return list<array{loc:string, lastmod:?string, priority:?string}>
     */
    private function objects(?string $lastmod): array
    {
        $ids = [];
        foreach ($this->api->dwarfPlanets(true) as $object) {
            $ids[$object->id] = true;
        }
        foreach (['planet', 'moon'] as $type) {
            foreach ($this->walkObjects($type) as $id) {
                $ids[$id] = true;
            }
        }
        foreach ($this->api->periodicComets(300) as $object) {
            $ids[$object->id] = true;
        }
        foreach ($this->api->neos(300) as $object) {
            $ids[$object->id] = true;
        }
        foreach ($this->api->tnos(300) as $object) {
            $ids[$object->id] = true;
        }

        $entries = [];
        foreach (array_keys($ids) as $id) {
            if ($id === '') {
                continue;
            }
            $entry = $this->entry(IndexableUrl::forRoute('objects.show', ['slug' => $id]), $lastmod, '0.6');
            if ($entry !== null) {
                $entries[] = $entry;
            }
        }
        usort($entries, fn (array $a, array $b): int => $a['loc'] <=> $b['loc']);

        return $entries;
    }

    /**
     * @return array{0:list<array{loc:string, lastmod:?string, priority:?string}>, 1:list<array{loc:string, lastmod:?string, priority:?string}>}
     */
    private function exoplanetSections(?string $catalogue): array
    {
        $planets = $this->walkExoplanets();
        $exoplanets = [];
        /** @var array<string, ?string> $hosts */
        $hosts = [];
        foreach ($planets as $planet) {
            $lastmod = $this->timestamp($planet->retrievedAt) ?? $catalogue;
            $entry = $this->entry(IndexableUrl::forRoute('exoplanets.show', ['id' => $planet->id]), $lastmod, '0.5');
            if ($entry !== null) {
                $exoplanets[$entry['loc']] = $entry;
            }
            $host = trim($planet->hostId);
            if ($host === '') {
                continue;
            }
            $previous = $hosts[$host] ?? null;
            $hosts[$host] = $this->later($previous, $lastmod);
        }

        $systems = [];
        foreach ($hosts as $id => $lastmod) {
            $entry = $this->entry(IndexableUrl::forRoute('systems.show', ['id' => $id]), $lastmod, '0.5');
            if ($entry !== null) {
                $systems[] = $entry;
            }
        }
        usort($systems, fn (array $a, array $b): int => $a['loc'] <=> $b['loc']);

        return [array_values($exoplanets), $systems];
    }

    /**
     * @return list<array{loc:string, lastmod:?string, priority:?string}>
     */
    private function releases(): array
    {
        try {
            if (! Schema::hasTable('community_releases')) {
                return [];
            }
            $published = app(ReleaseCatalog::class)->published();
        } catch (Throwable $e) {
            Log::warning('Sitemap skipped release notes because they could not be read.', ['exception' => $e]);

            return [];
        }

        $entries = [];
        foreach ($published as $release) {
            $entry = $this->entry(
                IndexableUrl::forRoute('releases.show', ['version' => $release->version]),
                $this->timestamp($release->published_at->toIso8601String()),
                '0.4',
            );
            if ($entry !== null) {
                $entries[] = $entry;
            }
        }

        return $entries;
    }

    /**
     * Dated object-of-the-day permalinks. /today itself redirects, so it is not
     * listed. A date whose featured body is missing from a healthy catalogue
     * would 404 and is left out. An unreachable catalogue still serves the
     * page (a degradation panel, not a 404), so those dates stay.
     *
     * @return list<array{loc:string, lastmod:?string, priority:?string}>
     */
    private function today(?string $lastmod): array
    {
        $start = CarbonImmutable::parse(ObjectOfTheDay::FIRST_DATE, 'UTC')->startOfDay();
        $end = ObjectOfTheDay::today();
        /** @var array<string, true> $missing */
        $missing = [];
        $seen = [];

        for ($day = $start; $day->lte($end); $day = $day->addDay()) {
            $seen[ObjectOfTheDay::slugFor($day)] = true;
        }

        $unreachable = false;
        foreach (array_keys($seen) as $slug) {
            if ($unreachable) {
                break;
            }
            try {
                $object = $this->api->object($slug);
            } catch (SolarApiUnavailableException) {
                // The dated page stays up with a degradation panel, so it is
                // not a 404. One failure is enough; don't time out on the rest.
                $unreachable = true;

                continue;
            } catch (SolarApiException) {
                $missing[$slug] = true;

                continue;
            }
            if (! $object instanceof ObjectDetail) {
                $missing[$slug] = true;
            }
        }

        $entries = [];
        for ($day = $start; $day->lte($end); $day = $day->addDay()) {
            $date = $day->format('Y-m-d');
            if (isset($missing[ObjectOfTheDay::slugFor($day)]) || ObjectOfTheDay::parse($date) === null) {
                continue;
            }
            $entry = $this->entry(IndexableUrl::forRoute('today.show', ['date' => $date]), $lastmod, '0.4');
            if ($entry !== null) {
                $entries[] = $entry;
            }
        }

        return $entries;
    }

    /** @return list<string> */
    private function walkObjects(string $type): array
    {
        $ids = [];
        $offset = 0;
        $guard = 0;
        do {
            $page = $this->api->objects(['type' => $type], 100, $offset);
            foreach ($page->items as $object) {
                if ($object->id !== '') {
                    $ids[] = $object->id;
                }
            }
            if (! $page->hasMore) {
                break;
            }
            $next = $page->nextOffset();
            if ($next <= $offset) {
                throw new SolarApiException('Object catalogue page did not advance.');
            }
            $offset = $next;
            $guard++;
        } while ($guard < 200);

        if ($guard >= 200) {
            throw new SolarApiException('Object catalogue page walk did not finish.');
        }

        return $ids;
    }

    /** @return list<Exoplanet> */
    private function walkExoplanets(): array
    {
        /** @var array<string, Exoplanet> $planets */
        $planets = [];
        $offset = 0;
        $guard = 0;
        do {
            $page = $this->api->exoplanetCataloguePage($offset);
            if ($page->hasMore && $page->items === []) {
                throw new SolarApiException('Exoplanet catalogue page did not advance.');
            }
            foreach ($page->items as $planet) {
                $planets[$planet->id] = $planet;
            }
            if (! $page->hasMore) {
                break;
            }
            $next = $offset + $page->count();
            if ($next <= $offset) {
                throw new SolarApiException('Exoplanet catalogue page did not advance.');
            }
            $offset = $next;
            $guard++;
        } while ($guard < 30);

        if ($guard >= 30) {
            throw new SolarApiException('Exoplanet catalogue page walk did not finish.');
        }

        return array_values($planets);
    }

    private function catalogueLastmod(): ?string
    {
        $identity = app(CatalogueContext::class)->current()['identity'];
        if ($identity->known()) {
            $built = $this->timestamp($identity->builtAt);
            if ($built !== null) {
                return $built;
            }
        }

        try {
            return $this->timestamp($this->api->stats()->lastRefreshed()?->toIso8601String());
        } catch (SolarApiException) {
            return null;
        }
    }

    private function timestamp(?string $value): ?string
    {
        if ($value === null || ! CatalogueIdentity::isTimestamp($value)) {
            return null;
        }

        $parsed = CarbonImmutable::parse($value)->utc();
        $fraction = rtrim($parsed->format('u'), '0');
        $stamp = $parsed->format('Y-m-d\TH:i:s');
        if ($fraction !== '') {
            $stamp .= '.'.$fraction;
        }

        return $stamp.'Z';
    }

    private function later(?string $left, ?string $right): ?string
    {
        if ($left === null) {
            return $right;
        }
        if ($right === null) {
            return $left;
        }

        return CarbonImmutable::parse($left)->greaterThan(CarbonImmutable::parse($right)) ? $left : $right;
    }

    /**
     * @param  list<array{loc:string, lastmod:?string, priority:?string}>  $entries
     */
    private function latest(array $entries): ?string
    {
        $latest = null;
        foreach ($entries as $entry) {
            $latest = $this->later($latest, $entry['lastmod']);
        }

        return $latest;
    }

    /**
     * @return array{loc:string, lastmod:?string, priority:?string}|null
     */
    private function entry(?string $loc, ?string $lastmod, ?string $priority): ?array
    {
        if ($loc === null) {
            return null;
        }

        return ['loc' => $loc, 'lastmod' => $lastmod, 'priority' => $priority];
    }
}
