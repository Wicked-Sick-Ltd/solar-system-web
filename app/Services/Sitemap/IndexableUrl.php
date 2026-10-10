<?php

declare(strict_types=1);

namespace App\Services\Sitemap;

use App\Support\Links;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;

/**
 * A canonical URL is published only when generating it and matching it land on
 * the same route with the same parameters. That drops ids the router would 404
 * (a slash the pattern does not allow, a blank segment, a colliding path).
 */
final class IndexableUrl
{
    /** @param array<string, string> $parameters */
    public static function forRoute(string $name, array $parameters = []): ?string
    {
        if (! Route::has($name)) {
            return null;
        }

        try {
            $generated = route($name, $parameters);
        } catch (Throwable) {
            return null;
        }

        $path = parse_url($generated, PHP_URL_PATH);
        if (! is_string($path) || $path === '') {
            // route('home') is the origin with no path. The router still matches "/".
            $path = '/';
        }

        // Provisional catalogue ids keep a literal slash. The generated path
        // must round-trip through the router as that slash, not as %2F.
        $path = str_replace('%2F', '/', $path);
        if (! self::matches($name, $parameters, $path)) {
            return null;
        }

        $canonical = Links::canonical($generated);
        $canonical = self::withPath($canonical, $path);
        if (! self::acceptable($canonical)) {
            return null;
        }

        return $canonical;
    }

    /** @param array<string, string> $parameters */
    private static function matches(string $name, array $parameters, string $path): bool
    {
        try {
            $matched = Route::getRoutes()->match(Request::create($path, 'GET'));
        } catch (HttpException) {
            return false;
        }

        if ($matched->getName() !== $name) {
            return false;
        }

        foreach ($parameters as $key => $value) {
            $actual = $matched->parameter($key);
            if (! is_string($actual) || rawurldecode($actual) !== $value) {
                return false;
            }
        }

        return true;
    }

    private static function withPath(string $url, string $path): string
    {
        $parts = parse_url($url);
        if (! is_array($parts) || ! isset($parts['scheme'], $parts['host'])) {
            return $url;
        }

        $rebuilt = $parts['scheme'].'://'.$parts['host'];
        if (isset($parts['port'])) {
            $rebuilt .= ':'.$parts['port'];
        }

        if ($path === '/') {
            return $rebuilt;
        }

        return $rebuilt.$path;
    }

    private static function acceptable(string $url): bool
    {
        return (bool) preg_match('#\Ahttps?://#', $url)
            && strlen($url) >= 12
            && strlen($url) <= 2048;
    }
}
