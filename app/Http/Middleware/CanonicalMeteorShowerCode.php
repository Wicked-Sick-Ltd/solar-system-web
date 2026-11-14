<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\MeteorShowerPage;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Send every other spelling of a shower code to the uppercase URL in one hop. */
final class CanonicalMeteorShowerCode
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! in_array($request->getMethod(), ['GET', 'HEAD'], true)) {
            return $next($request);
        }

        $code = $request->route('code');
        if (! is_string($code) || $code === '') {
            return $next($request);
        }

        $canonical = MeteorShowerPage::canonicalCode($code);
        if ($canonical === '' || $code === $canonical) {
            return $next($request);
        }

        $location = route('meteor-showers.show', ['code' => $canonical]);
        $query = (string) $request->server->get('QUERY_STRING', '');
        if ($query !== '' && preg_match('/[\r\n]/', $query) !== 1) {
            $location .= '?'.$query;
        }

        return redirect()->to($location, 301);
    }
}
