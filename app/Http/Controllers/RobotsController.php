<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Support\Links;
use Illuminate\Http\Response;

final class RobotsController extends Controller
{
    public function __invoke(): Response
    {
        $lines = [
            'User-agent: *',
            'Allow: /',
            // Thin / non-canonical surfaces — no SEO value.
            'Disallow: /search',
            'Disallow: /random',
            '',
            // On the canonical host, whichever alias served this request.
            'Sitemap: '.Links::canonical(route('sitemap')),
            '',
        ];

        return response(implode("\n", $lines))->withHeaders(['Content-Type' => 'text/plain; charset=UTF-8', 'Cache-Control' => 'public, max-age=86400']);
    }
}
