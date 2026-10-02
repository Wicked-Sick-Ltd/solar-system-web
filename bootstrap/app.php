<?php

use App\Http\Middleware\PrivateNightWeather;
use App\Http\Middleware\PrivateObservingShortlist;
use App\Http\Middleware\PrivateObservingSync;
use App\Http\Middleware\SetResponseHeaders;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Global prepend so on the response it runs outermost — after Livewire's
        // globally-pushed back-button-cache middleware — letting our public
        // Cache-Control win on the cacheable routes (interactive pages keep
        // Livewire's no-store).
        $middleware->prepend([SetResponseHeaders::class, PrivateObservingSync::class, PrivateNightWeather::class, PrivateObservingShortlist::class]);
    })
    // Bind the exception handler. /api is HTML Livewire, not a JSON API.
    ->withExceptions()
    ->create();
