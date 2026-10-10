<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Support\Links;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * /api on this site is the developer guide. Catalogue paths such as
 * /api/v1/search belong on the catalogue host. Send GET there so a copied
 * path still reaches the read-only API.
 */
final class CataloguePathRedirect extends Controller
{
    public function __invoke(Request $request, string $path): RedirectResponse
    {
        $origin = Links::catalogueOrigin();
        $parts = parse_url($origin);
        if (! is_array($parts) || ! in_array($parts['scheme'] ?? '', ['http', 'https'], true) || ! isset($parts['host'])) {
            abort(404);
        }

        $encoded = [];
        foreach (explode('/', $path) as $segment) {
            if ($segment === '' || $segment === '.' || $segment === '..' || str_contains($segment, '\\') || preg_match('/[\x00-\x1F\x7F]/', $segment) === 1) {
                abort(404);
            }
            $encoded[] = rawurlencode($segment);
        }

        $target = $origin.'/api/v1/'.implode('/', $encoded);
        $query = $request->getQueryString();
        if (is_string($query) && $query !== '') {
            $target .= '?'.$query;
        }

        return redirect()
            ->away($target, 302)
            ->header('Cache-Control', 'public, max-age=300');
    }
}
