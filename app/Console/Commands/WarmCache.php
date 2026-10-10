<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Sitemap\SitemapBuilder;
use App\Services\SolarApi\Exceptions\SolarApiException;
use App\Services\SolarApi\SolarApiClient;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * Pre-warms the API response caches for the hot paths, so the first visitor
 * after the backend's nightly refresh doesn't pay the cold-fetch cost. Mirrors
 * the exact client calls the landing pages make so the right cache keys are
 * primed. Scheduled daily; safe to run by hand any time.
 */
final class WarmCache extends Command
{
    protected $signature = 'solar:warm-cache';

    protected $description = 'Pre-warm the Solar API caches for the hot landing pages';

    public function handle(SolarApiClient $api): int
    {
        // Warm shared reference + listing calls (same signatures the pages use).
        $tasks = [
            'stats' => fn () => $api->stats(),
            'sources' => fn () => $api->sources(),
            'objects (page 1)' => fn () => $api->objects([], 24, 0),
            'planets' => fn () => $api->objects(['type' => 'planet'], 12, 0),
            'dwarf planets' => fn () => $api->dwarfPlanets(true),
            'asteroids (page 1)' => fn () => $api->objects(['type' => 'asteroid'], 24, 0),
            'comets (page 1)' => fn () => $api->objects(['type' => 'comet'], 24, 0),
            'tnos (page 1)' => fn () => $api->objects(['type' => 'tno'], 24, 0),
        ];

        // Plus the well-known bodies that anchor the homepage + orrery.
        $bodies = [
            'planet-mercury', 'planet-venus', 'planet-earth', 'planet-mars',
            'planet-jupiter', 'planet-saturn', 'planet-uranus', 'planet-neptune',
            'dwarf-ceres', 'dwarf-pluto',
        ];
        foreach ($bodies as $id) {
            $tasks["object {$id}"] = fn () => $api->object($id);
        }

        $ok = 0;
        $failed = 0;
        foreach ($tasks as $label => $task) {
            try {
                $task();
                $ok++;
            } catch (SolarApiException $e) {
                $failed++;
                $this->warn("  ! {$label}: {$e->getMessage()}");
            }
        }

        // Rebuild the sitemap from the freshly-warmed catalogue so the next
        // hit, including a search-engine fetch, does not pay for a cold walk.
        try {
            Cache::forget('sitemap.xml');
            Cache::forget(SitemapBuilder::CACHE_KEY);
            app(SitemapBuilder::class)->remember();
        } catch (Throwable $e) {
            $this->warn('  ! sitemap: '.$e->getMessage());
        }

        $this->info("Warmed {$ok} cache entries".($failed ? ", {$failed} failed" : '').'.');

        // A degraded backend isn't a scheduler failure — we tried.
        return self::SUCCESS;
    }
}
