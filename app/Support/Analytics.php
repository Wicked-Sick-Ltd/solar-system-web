<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Http\Request;

/**
 * What the site may tell Google Analytics about the address it is on.
 *
 * gtag takes page_location from the address bar unless it is told otherwise,
 * so any sensitive query string reaches Google the moment a visitor who has
 * accepted analytics opens the link — before they touch anything on the page.
 * Settings share links carry an observing location in the URL fragment
 * (`#s=…`), which never reaches this request. Older `?s=` links still might,
 * so those query values are replaced here. The browser script reads that
 * value from a meta tag, including after Livewire navigation, instead of
 * the address bar.
 */
final class Analytics
{
    /** Query parameters whose values never leave the first party. `s` is the settings share token. */
    public const array REDACTED_QUERY_PARAMS = ['s'];

    /** Stands in for a redacted value, so "a share link was opened" still counts without the payload. */
    public const string REDACTED = 'redacted';

    /**
     * GA4 measurement id from GA4_MEASUREMENT_ID, or null when analytics must not render.
     * Anything that is not a measurement id is treated as unset so it cannot be echoed into HTML.
     */
    public static function measurementId(): ?string
    {
        $id = config('site.analytics.ga_measurement_id');

        if (! is_string($id)) {
            return null;
        }

        $id = strtoupper(trim($id));

        return preg_match('/\AG-[A-Z0-9]{4,32}\z/', $id) === 1 ? $id : null;
    }

    /** First-party consent script. Empty when the file cannot be read. */
    public static function clientScript(): string
    {
        $script = file_get_contents(resource_path('js/analytics-consent.js'));

        return is_string($script) ? $script : '';
    }

    /** The current URL with those parameters redacted, for gtag's page_location. */
    public static function pageLocation(?Request $request = null): string
    {
        $request ??= request();

        $query = $request->query();
        foreach (self::REDACTED_QUERY_PARAMS as $key) {
            if (array_key_exists($key, $query)) {
                $query[$key] = self::REDACTED;
            }
        }

        $url = $request->url();

        return $query === [] ? $url : $url.'?'.http_build_query($query);
    }
}
