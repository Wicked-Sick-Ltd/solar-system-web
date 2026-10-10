<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Services\Mailchimp\MailchimpClient;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Adds baseline security headers to every response, and makes the
 * non-interactive public pages edge-cacheable.
 *
 * Registered as a *global prepend* so on the response path it runs outermost —
 * after Livewire's back-button-cache middleware — letting it overwrite the
 * `no-store` that Livewire forces, on the routes where caching is safe.
 *
 * No Content-Security-Policy is set: the site uses a couple of inline scripts
 * (the no-FOUC theme switch) and inline handlers, so a strict CSP would need
 * refactoring first.
 */
final class SetResponseHeaders
{
    /**
     * Routes with no Livewire round-trips — safe to serve cookie-less and cache
     * at the edge. Interactive pages (filters, search, sort, pagination) are
     * deliberately excluded: they need the session for CSRF on wire:* updates.
     *
     * When Mailchimp is configured the shared footer mounts a Livewire signup
     * form, so these routes need the session too and are skipped at runtime.
     */
    private const CACHEABLE_ROUTES = ['home', 'planets.index', 'about', 'educators', 'higher-education', 'higher-education.handout', 'plugin', 'api', 'dwarf-planets'];

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $this->addSecurityHeaders($response);
        // Shared caches must select the anonymous representation before lookup,
        // and account pages must not remain in the browser cache after logout.
        $response->setVary('Cookie', false);
        if ($request->is('feedback', 'observe/night', PrivateNightWeather::PATH, PrivateObservingShortlist::PATH)) {
            // Include validation, throttle and exception responses for this private form.
            $response->headers->set('Cache-Control', 'private, no-store');
            $response->headers->set('Referrer-Policy', 'no-referrer');
        } elseif ($request->user() !== null || $request->cookies->count() > 0 || $request->headers->has('Authorization')) {
            $response->headers->set('Cache-Control', 'private, no-store');
        } else {
            $this->makeCacheable($request, $response);
        }

        return $response;
    }

    private function addSecurityHeaders(Response $response): void
    {
        $headers = [
            'X-Content-Type-Options' => 'nosniff',
            'X-Frame-Options' => 'SAMEORIGIN',
            'Referrer-Policy' => 'strict-origin-when-cross-origin',
            'Permissions-Policy' => 'geolocation=(self), camera=(), microphone=(), interest-cohort=()',
            'Cross-Origin-Opener-Policy' => 'same-origin',
        ];

        foreach ($headers as $name => $value) {
            if (! $response->headers->has($name)) {
                $response->headers->set($name, $value);
            }
        }
    }

    private function makeCacheable(Request $request, Response $response): void
    {
        if (! $request->isMethod('GET') || $response->getStatusCode() !== 200) {
            return;
        }

        if (! in_array($request->route()?->getName(), self::CACHEABLE_ROUTES, true)) {
            return;
        }

        // Header chrome is account-aware (sign in vs alerts/sign out), so a
        // logged-in response must never be stored in a shared cache.
        if ($request->user() !== null) {
            return;
        }

        // Livewire wire:submit needs the session CSRF token; cookie-less edge
        // cache would serve a token that cannot match the visitor's session.
        if (app(MailchimpClient::class)->isConfigured()) {
            return;
        }

        if (! str_contains((string) $response->headers->get('Content-Type', ''), 'text/html')) {
            return;
        }

        // Drop the session cookie so a shared cache (and the browser) can't tie
        // the page to one visitor — these routes don't use the session.
        foreach ($response->headers->getCookies() as $cookie) {
            $response->headers->removeCookie($cookie->getName(), $cookie->getPath(), $cookie->getDomain());
        }

        // Interactive catalogue calls echo the visitor's parameters, including
        // an observer location, so that response stays private.
        if ($request->routeIs('api') && $request->filled('try')) {
            $response->headers->set('Cache-Control', 'private, no-store');

            return;
        }

        // Catalogue-bearing HTML must not hide a changed observed build behind
        // the previous day's stale edge response. Static API guidance retains
        // its existing longer policy. Already cached responses need rollout purge.
        $cataloguePage = in_array($request->route()->getName(), ['home', 'planets.index', 'about', 'dwarf-planets'], true);
        $response->headers->set(
            'Cache-Control',
            $cataloguePage ? 'public, max-age=0, s-maxage=60' : 'public, max-age=0, s-maxage=600, stale-while-revalidate=86400',
        );
    }
}
