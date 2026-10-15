<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Small helpers for links that are derived from configuration rather than
 * hard-coded, so they follow the backend — and this site's canonical host —
 * wherever they are deployed.
 */
final class Links
{
    /**
     * Rewrite a URL generated for the current request onto the canonical host
     * (APP_URL). The site answers on more than one hostname; whichever one
     * served the request, canonical tags, OG URLs, JSON-LD, the sitemap and
     * robots.txt must all name the canonical one. Root-relative paths are made
     * absolute on it; URLs on any other host (CDN assets, external links) are
     * returned untouched.
     */
    public static function canonical(string $url): string
    {
        $root = rtrim((string) config('app.url'), '/');
        if ($root === '') {
            return $url;
        }

        if (str_starts_with($url, '/') && ! str_starts_with($url, '//')) {
            return $root.$url;
        }

        $requestRoot = rtrim(url()->to('/'), '/');
        if ($url === $requestRoot) {
            return $root;
        }

        if (str_starts_with($url, $requestRoot.'/') || str_starts_with($url, $requestRoot.'?')) {
            return $root.substr($url, strlen($requestRoot));
        }

        return $url;
    }

    /** The public catalogue API root (what visitors should be sent to), e.g. https://api.publicuniverse.net/api/v1. */
    public static function cataloguePublicBase(): string
    {
        $public = (string) config('services.solar.public_url');

        return rtrim($public !== '' ? $public : (string) config('services.solar.base_url'), '/');
    }

    /** Public host of the catalogue API, without the /api/v1 suffix — for links, never for fetching. */
    public static function catalogueOrigin(): string
    {
        $base = self::cataloguePublicBase();
        $host = preg_replace('#/api/v\d+/?$#', '', $base) ?? $base;

        return rtrim($host, '/');
    }

    /** The backend's interactive OpenAPI docs (Swagger UI at /docs). */
    public static function apiDocs(): string
    {
        if ($explicit = config('site.api_docs_url')) {
            return $explicit;
        }

        return self::catalogueOrigin().'/docs';
    }

    /** The raw OpenAPI JSON. */
    public static function openApi(): string
    {
        return self::catalogueOrigin().'/openapi.json';
    }

    /** The backend's streamable HTTP MCP endpoint. */
    public static function mcp(): string
    {
        return self::catalogueOrigin().'/mcp';
    }
}
