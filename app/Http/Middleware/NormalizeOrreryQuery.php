<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\OrreryQuery;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Collapse unknown or impossible /orrery queries to the whitelisted form. */
final class NormalizeOrreryQuery
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! in_array($request->getMethod(), ['GET', 'HEAD'], true)) {
            return $next($request);
        }

        $clamped = OrreryQuery::redirectQuery(
            $request->query->all(),
            (string) $request->server->get('QUERY_STRING', ''),
        );
        if ($clamped !== null) {
            return redirect()->route('orrery', $clamped, 301);
        }

        return $next($request);
    }
}
