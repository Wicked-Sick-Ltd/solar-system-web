<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\Sitemap\SitemapBuilder;
use Illuminate\Http\Response;

/**
 * Sitemap index at /sitemap.xml, with one child urlset per kind of page.
 * The documents are cached (a day when the catalogue read completed, otherwise
 * ten minutes) and every <loc> is on the canonical host.
 */
final class SitemapController extends Controller
{
    public function index(SitemapBuilder $sitemap): Response
    {
        return $this->xml($sitemap->remember()['index']);
    }

    public function child(string $name, SitemapBuilder $sitemap): Response
    {
        $xml = $sitemap->remember()['children'][$name] ?? null;
        abort_if(! is_string($xml), 404);

        return $this->xml($xml);
    }

    private function xml(string $xml): Response
    {
        return response($xml, 200, [
            'Content-Type' => 'application/xml; charset=UTF-8',
            'Cache-Control' => 'public, max-age=3600, s-maxage=86400',
        ]);
    }
}
