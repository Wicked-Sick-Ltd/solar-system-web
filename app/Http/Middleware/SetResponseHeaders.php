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
    private const CACHEABLE_ROUTES = ['home', 'planets.index', 'about', 'api', 'dwarf-planets'];

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $this->addSecurityHeaders($response);
        $this->makeCacheable($request, $response);

        return $response;
    }

    private function addSecurityHeaders(Response $response): void
    {
        $headers = [
            'X-Content-Type-Options' => 'nosniff',
            'X-Frame-Options' => 'SAMEORIGIN',
            'Referrer-Policy' => 'strict-origin-when-cross-origin',
            'Permissions-Policy' => 'geolocation=(), camera=(), microphone=(), interest-cohort=()',
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

        // Short browser cache; longer shared (CDN) cache; never-blocking refresh.
        // The catalogue only changes nightly, so this is comfortably safe.
        $response->headers->set(
            'Cache-Control',
            'public, max-age=120, s-maxage=600, stale-while-revalidate=86400',
        );
    }
}
