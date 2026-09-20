<?php

declare(strict_types=1);

namespace App\Services\What3Words;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * Converts a what3words address to coordinates via the what3words v3 API.
 * Disabled (enabled() === false) when no key is configured, in which case
 * the observer panel neither offers nor accepts what3words addresses.
 *
 * Nothing is cached or logged: an address someone looked up is a record of
 * where they were, and the promise on the page is that we hold none. Each
 * lookup goes straight to what3words and the rounded (2 dp) result goes
 * straight back to the visitor's browser. Repeat lookups cost API quota;
 * that is the price of the promise.
 */
final class What3WordsClient
{
    private const ENDPOINT = 'https://api.what3words.com/v3/convert-to-coordinates';

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
    }
}
