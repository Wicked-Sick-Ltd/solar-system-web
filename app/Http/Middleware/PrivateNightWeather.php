<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use JsonException;
use Symfony\Component\HttpFoundation\Response;

/** Bound the private body before the framework decodes or normalises its values. */
final class PrivateNightWeather
{
    public const string PATH = 'observe/night/weather';

    public const int MAX_BYTES = 4096;

    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->is(self::PATH)) {
            return $next($request);
        }
        $status = null;
        if ($request->query->count() !== 0) {
            $status = 422;
        } elseif ($request->isMethod('POST')) {
            $type = strtolower(trim(explode(';', (string) $request->header('Content-Type'))[0]));
            if (! in_array($type, ['application/json', 'application/x-www-form-urlencoded'], true)) {
                $status = 415;
            } elseif ((int) $request->header('Content-Length', '0') > self::MAX_BYTES) {
                $status = 413;
            } else {
                $stream = $request->getContent(true);
                $raw = stream_get_contents($stream, self::MAX_BYTES + 1);
                if ($raw === false || strlen($raw) > self::MAX_BYTES) {
                    $status = 413;
                } else {
                    try {
                        $input = $type === 'application/json' ? json_decode($raw, false, 8, JSON_THROW_ON_ERROR) : (object) $request->request->all();
                        if (! is_object($input)) {
                            $status = 422;
                        } else {
                            $request->attributes->set('nightWeatherInput', (array) $input);
                        }
                    } catch (JsonException) {
                        $status = 422;
                    }
                }
            }
        }
        if ($status !== null) {
            $problem = 'The forecast request is invalid or too large. Return to the planner and try again.';

            return $request->expectsJson() ? response()->json(['status' => 'invalid_request', 'message' => $problem], $status)
                : response()->view('observing.night-weather', ['forecast' => null, 'problem' => $problem], $status);
        }

        return $next($request);
    }
}
