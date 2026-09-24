<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Small helpers for external links that are derived from configuration rather
 * than hard-coded, so they follow the backend wherever it is deployed.
 */
final class Links
{
    /** The backend's interactive OpenAPI docs (Swagger UI at /docs). */
    public static function apiDocs(): string
    {
        if ($explicit = config('site.api_docs_url')) {
            return $explicit;
        }

        // Strip the /api/v1 suffix from the base URL and point at /docs.
        $base = (string) config('services.solar.base_url');
        $host = preg_replace('#/api/v\d+/?$#', '', $base);

        return rtrim($host, '/').'/docs';
    }

    /** The raw OpenAPI JSON. */
    public static function openApi(): string
    {
        $base = (string) config('services.solar.base_url');
        $host = preg_replace('#/api/v\d+/?$#', '', $base);

        return rtrim($host, '/').'/openapi.json';
    }

    /** The backend's streamable HTTP MCP endpoint. */
    public static function mcp(): string
    {
        $base = (string) config('services.solar.base_url');
        $host = preg_replace('#/api/v\d+/?$#', '', $base);

        return rtrim($host, '/').'/mcp';
    }
}
