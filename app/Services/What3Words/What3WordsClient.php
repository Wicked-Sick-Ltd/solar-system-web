<?php

declare(strict_types=1);

namespace App\Services\What3Words;

use App\Support\LocationParser;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Converts between what3words addresses and coordinates via the v3 API.
 * Disabled (enabled() === false) when no key is configured, in which case
 * callers neither offer nor accept what3words addresses.
 *
 * Sky-panel pastes use toCoordinates() and are not cached: that page promises
 * the address and the result are not stored. Observatory site lookup uses
 * locate() and wordsFor(), which cache a successful result so the same
 * address or rounded coordinate is not requested again. Cache keys carry no
 * account id and no IP address.
 */
final class What3WordsClient
{
    private const CONVERT = 'https://api.what3words.com/v3/convert-to-coordinates';

    private const REVERSE = 'https://api.what3words.com/v3/convert-to-3wa';

    public function enabled(): bool
    {
        return trim((string) (config('services.what3words.key') ?? '')) !== '';
    }

    /**
     * Sky-panel conversion. Deliberately uncached.
     *
     * @return array{lat: float, lon: float}
     *
     * @throws What3WordsException with a visitor-safe message
     */
    public function toCoordinates(string $words): array
    {
        $words = mb_strtolower(trim($words, " /\t\n"));
        $body = $this->convert(self::CONVERT, ['words' => $words]);

        return [
            'lat' => round((float) $body['coordinates']['lat'], 2),
            'lon' => round((float) $body['coordinates']['lng'], 2),
        ];
    }

    /**
     * Observatory lookup of a three-word address. Successful results are cached.
     *
     * @return array{words: string, latitude: float, longitude: float, roundedLatitude: float, roundedLongitude: float, nearestPlace: ?string}
     *
     * @throws What3WordsException with a visitor-safe message
     */
    public function locate(string $address): array
    {
        $words = LocationParser::what3words($address);
        if ($words === null) {
            throw new What3WordsException(__('Enter a what3words address as three words, such as ///filled.count.soap.'), 'invalid');
        }
        if (! $this->enabled()) {
            throw new What3WordsException(__('what3words addresses aren\'t available here right now.'), 'disabled');
        }

        $key = 'w3w:forward:'.hash('sha256', $words);
        $cached = Cache::get($key);
        if ($this->isPlace($cached)) {
            return $cached;
        }

        $place = $this->placeFromBody($words, $this->convert(self::CONVERT, ['words' => $words]));
        Cache::put($key, $place, $this->ttl());

        return $place;
    }

    /**
     * Approximate three-word address for coordinates already rounded to 2 dp.
     * Pass $live false to read the cache only.
     *
     * @return array{words: string, nearestPlace: ?string, latitude: float, longitude: float}|null
     */
    public function wordsFor(float $lat, float $lon, bool $live = true): ?array
    {
        if (! $this->enabled() || ! $this->validCoordinate($lat, $lon)) {
            return null;
        }
        $lat = round($lat, 2);
        $lon = round($lon, 2);
        $key = sprintf('w3w:reverse:%.2f:%.2f', $lat, $lon);
        $cached = Cache::get($key);
        if ($this->isReverse($cached)) {
            return $cached;
        }
        if (! $live) {
            return null;
        }

        try {
            $body = $this->convert(self::REVERSE.'?coordinates='.sprintf('%.2f,%.2f', $lat, $lon));
        } catch (What3WordsException) {
            return null;
        }

        $words = LocationParser::what3words((string) ($body['words'] ?? ''));
        if ($words === null) {
            return null;
        }

        $result = [
            'words' => $words,
            'nearestPlace' => $this->nearestPlace($body),
            'latitude' => $lat,
            'longitude' => $lon,
        ];
        Cache::put($key, $result, $this->ttl());

        return $result;
    }

    /**
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>
     */
    private function convert(string $url, array $query = []): array
    {
        if (! $this->enabled()) {
            throw new What3WordsException(__('what3words addresses aren\'t available here right now.'), 'disabled');
        }

        try {
            $pending = Http::timeout(6)
                ->withHeaders(['X-Api-Key' => (string) config('services.what3words.key')])
                ->acceptJson();
            $response = $query === [] ? $pending->get($url) : $pending->get($url, $query);
        } catch (ConnectionException) {
            throw new What3WordsException(__('what3words is unavailable right now — try coordinates instead.'), 'unavailable');
        }

        $body = $response->json();
        $code = is_array($body) ? ($body['error']['code'] ?? null) : null;

        if ($code === 'BadWords' || $code === 'InvalidWords' || $response->status() === 404) {
            throw new What3WordsException(__('That what3words address wasn\'t recognised — check the three words.'), 'unrecognised');
        }
        if (! $response->successful() || ! is_array($body) || ! isset($body['coordinates']['lat'], $body['coordinates']['lng'])) {
            throw new What3WordsException(__('what3words is unavailable right now — try coordinates instead.'), 'unavailable');
        }

        return $body;
    }

    /**
     * @param  array<string, mixed>  $body
     * @return array{words: string, latitude: float, longitude: float, roundedLatitude: float, roundedLongitude: float, nearestPlace: ?string}
     */
    private function placeFromBody(string $words, array $body): array
    {
        $lat = (float) $body['coordinates']['lat'];
        $lon = (float) $body['coordinates']['lng'];

        return [
            'words' => $words,
            'latitude' => round($lat, 6),
            'longitude' => round($lon, 6),
            'roundedLatitude' => round($lat, 2),
            'roundedLongitude' => round($lon, 2),
            'nearestPlace' => $this->nearestPlace($body),
        ];
    }

    /** @param  array<string, mixed>  $body */
    private function nearestPlace(array $body): ?string
    {
        $place = $body['nearestPlace'] ?? null;
        if (! is_string($place)) {
            return null;
        }
        $place = trim($place);
        if ($place === '') {
            return null;
        }

        return mb_substr($place, 0, 120);
    }

    private function validCoordinate(float $lat, float $lon): bool
    {
        return is_finite($lat) && is_finite($lon) && $lat >= -90 && $lat <= 90 && $lon >= -180 && $lon <= 180;
    }

    private function isPlace(mixed $cached): bool
    {
        return is_array($cached)
            && is_string($cached['words'] ?? null)
            && is_numeric($cached['latitude'] ?? null)
            && is_numeric($cached['longitude'] ?? null)
            && is_numeric($cached['roundedLatitude'] ?? null)
            && is_numeric($cached['roundedLongitude'] ?? null);
    }

    private function isReverse(mixed $cached): bool
    {
        return is_array($cached)
            && is_string($cached['words'] ?? null)
            && is_numeric($cached['latitude'] ?? null)
            && is_numeric($cached['longitude'] ?? null);
    }

    private function ttl(): int
    {
        return max(60, (int) config('services.what3words.cache_seconds', 604800));
    }
}
