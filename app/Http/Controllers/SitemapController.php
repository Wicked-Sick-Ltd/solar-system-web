<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\SolarApi\Exceptions\SolarApiException;
use App\Services\SolarApi\SolarApiClient;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;

/**
 * Dynamic sitemap built from the catalogue. Cached for 24 hours so it costs the
 * backend almost nothing. The catalogue is ~15k objects; we list the named,
 * browsable bodies (planets, dwarf planets, moons, notable small bodies) rather
 * than every faint designation — that's what's worth indexing.
 */
final class SitemapController extends Controller
{
    public function __invoke(SolarApiClient $api): Response
    {
        $xml = Cache::remember('sitemap.xml', now()->addDay(), fn () => $this->build($api));

        return response($xml, 200, [
            'Content-Type' => 'application/xml; charset=UTF-8',
            'Cache-Control' => 'public, max-age=3600, s-maxage=86400',
        ]);
    }

    private function build(SolarApiClient $api): string
    {
        $urls = [];

        // Static, high-value pages.
        foreach (['home', 'objects.index', 'planets.index', 'dwarf-planets',
            'asteroids', 'comets', 'tnos', 'orrery', 'exoplanets.index', 'galaxy', 'about', 'api', 'privacy'] as $name) {
            $urls[route($name)] = $name === 'home' ? '1.0' : '0.7';
        }

        // Object detail pages worth indexing.
        try {
            $slugs = [];
            foreach ($api->dwarfPlanets(true) as $o) {
                $slugs[] = $o->slug();
            }
            foreach (['planet', 'moon'] as $type) {
                $offset = 0;
                do {
                    $page = $api->objects(['type' => $type], 100, $offset);
                    foreach ($page->items as $o) {
                        $slugs[] = $o->slug();
                    }
                    $offset += 100;
                } while ($page->hasMore && $offset < 400);
            }
            foreach ($api->periodicComets(300) as $o) {
                $slugs[] = $o->slug();
            }
            foreach ($api->neos(300) as $o) {
                $slugs[] = $o->slug();
            }
            foreach ($api->tnos(300) as $o) {
                $slugs[] = $o->slug();
            }

            foreach (array_unique(array_filter($slugs)) as $slug) {
                $urls[route('objects.show', $slug)] = '0.6';
            }
        } catch (SolarApiException) {
            // A degraded backend still yields a valid sitemap of static pages.
        }

        $lastmod = now()->toAtomString();

        $body = '';
        foreach ($urls as $loc => $priority) {
            $body .= sprintf(
                "  <url><loc>%s</loc><lastmod>%s</lastmod><priority>%s</priority></url>\n",
                htmlspecialchars($loc, ENT_XML1),
                $lastmod,
                $priority,
            );
        }

        return '<?xml version="1.0" encoding="UTF-8"?>'."\n"
            .'<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'."\n"
            .$body
            .'</urlset>'."\n";
    }
}
