<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use JsonException;
use Symfony\Component\HttpFoundation\Response;

/** Keep bounded private form input before Laravel's normalisation middleware. */
final class PrivateObservingShortlist
{
    public const string PATH = 'observe/shortlist';

    public const int MAX_BYTES = 16384;

    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->is(self::PATH)) {
            return $next($request);
        }
        $status = $request->query->count() !== 0 ? 422 : null;
        if ($status === null && ! $request->isMethod('POST')) {
            // GET/HEAD have no form payload; stop bodies before Laravel can decode JSON.
            $status = (int) $request->header('Content-Length', '0') > self::MAX_BYTES ? 413 : null;
            if ($status === null && stream_get_contents($request->getContent(true), 1) !== '') {
                $status = 422;
            }
        }
        if ($status === null && $request->isMethod('POST')) {
            $type = strtolower(trim(explode(';', (string) $request->header('Content-Type'))[0]));
            if (! in_array($type, ['application/json', 'application/x-www-form-urlencoded'], true)) {
                $status = 415;
            } elseif ((int) $request->header('Content-Length', '0') > self::MAX_BYTES) {
                $status = 413;
            } else {
                $raw = stream_get_contents($request->getContent(true), self::MAX_BYTES + 1);
                if ($raw === false || strlen($raw) > self::MAX_BYTES) {
                    $status = 413;
                } else {
                    try {
                        $input = $type === 'application/json' ? json_decode($raw, false, 8, JSON_THROW_ON_ERROR) : (object) $request->request->all();
                        if (! is_object($input)) {
                            $status = 422;
                        } else {
                            $request->attributes->set('shortlistInput', (array) $input);
                        }
                    } catch (JsonException) {
                        $status = 422;
                    }
                }
            }
        }
        if ($status !== null) {
            return response()->view('observing.shortlist-error', ['problem' => 'The shortlist request is invalid or too large. Enter the planning fields in the form and try again.'], $status);
        }

        return $next($request);
    }
}
