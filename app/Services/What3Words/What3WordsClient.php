<?php

declare(strict_types=1);

namespace App\Services\What3Words;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Converts a what3words address to coordinates via the what3words v3 API.
 * Disabled (enabled() === false) when no key is configured, in which case
 * the observer panel neither offers nor accepts what3words addresses.
 *
 * Results are cached for a day per address: a 3 m square never moves, and
 * the free plan is metered. Coordinates are rounded to 2 dp like every other
 * location path — we deliberately do not keep the precise square.
 */
final class What3WordsClient
{
    private const ENDPOINT = 'https://api.what3words.com/v3/convert-to-coordinates';

    private const CACHE_TTL = 86400;

    public function enabled(): bool
    {
        return (string) config('services.what3words.key') !== '';
    }

    /**
     * @return array{lat: float, lon: float}
     *
     * @throws What3WordsException with a visitor-safe message
     */
    public function toCoordinates(string $words): array
    {
        if (! $this->enabled()) {
            throw new What3WordsException(__('what3words addresses aren\'t available here right now.'));
        }

        $words = mb_strtolower(trim($words, " /\t\n"));

        return Cache::remember('w3w:'.sha1($words), self::CACHE_TTL, function () use ($words): array {
            try {
                $response = Http::timeout(6)
                    ->withHeaders(['X-Api-Key' => (string) config('services.what3words.key')])
                    ->acceptJson()
                    ->get(self::ENDPOINT, ['words' => $words]);
            } catch (ConnectionException) {
                throw new What3WordsException(__('what3words is unavailable right now — try coordinates instead.'));
            }

            $body = $response->json();
            $code = is_array($body) ? ($body['error']['code'] ?? null) : null;

            if ($code === 'BadWords' || $code === 'InvalidWords' || $response->status() === 404) {
                throw new What3WordsException(__('That what3words address wasn\'t recognised — check the three words.'));
            }
            if (! $response->successful() || ! is_array($body) || ! isset($body['coordinates']['lat'], $body['coordinates']['lng'])) {
                throw new What3WordsException(__('what3words is unavailable right now — try coordinates instead.'));
            }

            return [
                'lat' => round((float) $body['coordinates']['lat'], 2),
                'lon' => round((float) $body['coordinates']['lng'], 2),
            ];
        });
    }
}
