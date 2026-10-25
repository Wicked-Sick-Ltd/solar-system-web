<?php

use App\Http\Middleware\PrivateNightWeather;
use App\Http\Middleware\PrivateObservingShortlist;
use App\Http\Middleware\PrivateObservingSync;
use App\Http\Middleware\SetResponseHeaders;
use App\Support\Analytics;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Local nginx/PHP-FPM forwards the visitor scheme. Trust that peer
        // (and private LAN ranges) for For/Port/Proto only. A client-supplied
        // X-Forwarded-Host is ignored so it cannot spoof the host. Set
        // TRUSTED_PROXIES=* only when every connecting peer is a proxy.
        $trustedProxies = env('TRUSTED_PROXIES', '127.0.0.1,::1,10.0.0.0/8,172.16.0.0/12,192.168.0.0/16');
        $trustedProxies = is_string($trustedProxies) ? trim($trustedProxies) : '';
        $trustedProxies = $trustedProxies === '*'
            ? '*'
            : array_values(array_filter(
                array_map(trim(...), explode(',', $trustedProxies)),
                static fn (string $proxy): bool => $proxy !== '',
            ));
        $middleware->trustProxies(
            at: $trustedProxies,
            headers: Request::HEADER_X_FORWARDED_FOR
                | Request::HEADER_X_FORWARDED_PORT
                | Request::HEADER_X_FORWARDED_PROTO,
        );
        // Global prepend so on the response it runs outermost — after Livewire's
        // globally-pushed back-button-cache middleware — letting our public
        // Cache-Control win on the cacheable routes (interactive pages keep
        // Livewire's no-store).
        $middleware->prepend([SetResponseHeaders::class, PrivateObservingSync::class, PrivateNightWeather::class, PrivateObservingShortlist::class]);
        // The consent cookie is written and read by the page script. Leave it
        // unencrypted so the server can see an explicit "all" before rendering
        // the no-JavaScript Tag Manager image.
        $middleware->encryptCookies(except: [Analytics::CONSENT_COOKIE]);
    })
    // Bind the exception handler. /api is HTML Livewire, not a JSON API.
    ->withExceptions()
    ->create();
