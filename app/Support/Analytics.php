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
 * A settings share link (?s=…) carries an observing location, which belongs in
 * the visitor's own browser and nowhere else, so the layout publishes the page
 * location from here with those parameters replaced and the cookie banner
 * hands that to gtag instead.
 */
final class Analytics
{
    /** Query parameters whose values never leave the first party. `s` is the settings share token. */
    public const array REDACTED_QUERY_PARAMS = ['s'];

    /** Stands in for a redacted value, so "a share link was opened" still counts without the payload. */
    public const string REDACTED = 'redacted';

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
