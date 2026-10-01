<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use JsonException;
use Symfony\Component\HttpFoundation\InputBag;
use Symfony\Component\HttpFoundation\Response;

/** Runs before TrimStrings/ConvertEmptyStringsToNull can decode private JSON. */
final class PrivateObservingSync
{
    public const string PATH = 'account/observing-workspace';

    public const int MAX_BYTES = 1572864;

    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->is(self::PATH)) {
            return $next($request);
        }
        $request->headers->set('Accept', 'application/json');
        // CSRF is sent via the normal X-CSRF-TOKEN header, never in private data.
        $request->setJson(new InputBag([]));
        $request->request->replace([]);
        if ($request->query->count() !== 0) {
            return $this->error(400);
        }
        if (in_array($request->method(), ['PUT', 'DELETE'], true)) {
            if (strtolower(trim(explode(';', (string) $request->header('Content-Type'))[0])) !== 'application/json') {
                return $this->error(415);
            }
            if ((int) $request->header('Content-Length', '0') > self::MAX_BYTES) {
                return $this->error(413);
            }
            $stream = $request->getContent(true);
            $raw = stream_get_contents($stream, self::MAX_BYTES + 1);
            if ($raw === false || strlen($raw) > self::MAX_BYTES) {
                return $this->error(413);
            }
            try {
                $body = json_decode($raw, false, 32, JSON_THROW_ON_ERROR);
            } catch (JsonException) {
                return $this->error(422);
            }
            $request->attributes->set('observingSyncBody', $body);
        }
        $response = $next($request);
        // Framework auth, CSRF, throttle and method errors are also private JSON.
        if ($response->getStatusCode() >= 400 && ! $response->headers->has('X-Observing-Sync-Response')) {
            return $this->error($response->getStatusCode());
        }
        $response->headers->remove('X-Observing-Sync-Response');

        $this->protect($response);

        return $response;
    }

    private function error(int $status): JsonResponse
    {
        $response = response()->json(['error' => match ($status) {
            401 => 'authentication_required', 419 => 'session_expired', 413 => 'request_too_large',
            415 => 'json_required', 422 => 'invalid_document', 429 => 'too_many_requests', default => 'request_failed',
        }], $status);
        $this->protect($response);

        return $response;
    }

    private function protect(Response $response): void
    {
        $response->headers->set('Cache-Control', 'private, no-store');
        $response->headers->set('Referrer-Policy', 'no-referrer');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
    }
}
