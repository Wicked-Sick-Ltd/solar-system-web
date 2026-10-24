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
 * so those query values are replaced here. Observing `lat` and `lon` query
 * values are replaced too: the night tools refuse to copy them into the page,
 * and they must not reach Google either. The browser script reads that
 * value from a meta tag, including after Livewire navigation, instead of
 * the address bar.
 */
final class Analytics
{
    /**
     * Query parameters whose values never leave the first party.
     * `s` is the settings share token. `lat` and `lon` are an observing location.
     */
    public const array REDACTED_QUERY_PARAMS = ['s', 'lat', 'lon'];

    /** Try-it parameter names on /api that carry an observer location. */
    private const array REDACTED_TRY_PARAMS = ['lat', 'lon'];

    /** Stands in for a redacted value, so "a share link was opened" still counts without the payload. */
    public const string REDACTED = 'redacted';

    /**
     * First-party cookie set from the browser when the visitor chooses.
     * It is not encrypted: the page script has to read and write it, and the
     * server has to read the same value to decide whether a no-JavaScript
     * Tag Manager image is allowed.
     */
    public const string CONSENT_COOKIE = 'cookie_consent';

    /** The only value that records an explicit analytics opt-in. */
    public const string CONSENT_ACCEPTED = 'all';

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

    /**
     * Google Tag Manager container id from GTM_CONTAINER_ID, or null when Tag Manager must not render.
     * Anything that is not a container id is treated as unset so it cannot be echoed into HTML.
     */
    public static function gtmContainerId(): ?string
    {
        $id = config('site.analytics.gtm_container_id');

        if (! is_string($id)) {
            return null;
        }

        $id = strtoupper(trim($id));

        return preg_match('/\AGTM-[A-Z0-9]{4,16}\z/', $id) === 1 ? $id : null;
    }

    /** Consent UI runs when GA4, Tag Manager, or both are configured. */
    public static function enabled(): bool
    {
        return self::measurementId() !== null || self::gtmContainerId() !== null;
    }

    /**
     * True when this request carries the cookie written by "Accept analytics".
     * Anything else, including a missing cookie or "Essential only", is not consent.
     * The no-JavaScript Tag Manager image needs this check: that image cannot
     * run Consent Mode, and the banner cannot ask.
     */
    public static function hasServerOptIn(?Request $request = null): bool
    {
        $request ??= request();

        return $request->cookie(self::CONSENT_COOKIE) === self::CONSENT_ACCEPTED;
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
        if (isset($query['p']) && is_array($query['p'])) {
            foreach (self::REDACTED_TRY_PARAMS as $key) {
                if (array_key_exists($key, $query['p'])) {
                    $query['p'][$key] = self::REDACTED;
                }
            }
        }

        $url = $request->url();

        return $query === [] ? $url : $url.'?'.http_build_query($query);
    }
}
