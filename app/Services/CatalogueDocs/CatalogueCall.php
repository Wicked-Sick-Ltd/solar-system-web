<?php

declare(strict_types=1);

namespace App\Services\CatalogueDocs;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Throwable;

/**
 * Builds and performs a public GET that already appears in the cached OpenAPI
 * document. Path and query values stay on the configured catalogue origin.
 */
final class CatalogueCall
{
    public const LIMIT_CAP = 3;

    /**
     * @param  array<string, mixed>|null  $catalogue
     * @return array<string, mixed>
     */
    public function attempt(?array $catalogue, string $operationId, mixed $input): array
    {
        if (! preg_match('/^[A-Za-z0-9_.-]{1,120}$/', $operationId)) {
            return $this->failure($operationId, 'That operation is not published as a read-only GET.');
        }

        $operation = null;
        foreach ($catalogue['operations'] ?? [] as $candidate) {
            if (is_array($candidate) && ($candidate['id'] ?? null) === $operationId) {
                $operation = $candidate;
                break;
            }
        }
        if ($operation === null) {
            return $this->failure($operationId, 'That operation is not published as a read-only GET.');
        }

        $provided = $this->stringMap($input);
        $built = $this->build((string) ($catalogue['origin'] ?? ''), $operation, $provided);
        $result = [
            'id' => $operationId,
            'input' => $built['sent'] !== [] ? $built['sent'] : $provided,
        ];
        if ($built['url'] === null) {
            return $result + $this->failure($operationId, $built['error'] ?? 'The read-only request could not be built from those parameters.');
        }

        $cacheKey = 'catalogue-docs:call:'.hash('sha256', $built['url']);
        $cached = Cache::get($cacheKey);
        if (is_array($cached)) {
            return $result + $cached + ['ok' => true, 'url' => $built['url']];
        }

        $bucket = 'catalogue-docs:try:'.hash('sha256', (string) request()->ip());
        if (RateLimiter::tooManyAttempts($bucket, 10)) {
            return $result + $this->failure($operationId, 'Too many interactive calls from this network. Wait a minute and try again.');
        }
        RateLimiter::hit($bucket, 60);

        try {
            $response = $this->send($built['url']);
        } catch (ConnectionException) {
            return $result + $this->failure($operationId, 'The catalogue did not respond.');
        } catch (Throwable $exception) {
            Log::warning('Catalogue docs call failed', ['operation' => $operationId, 'message' => $exception->getMessage()]);

            return $result + $this->failure($operationId, 'The catalogue did not respond.');
        }

        if ($response->status() >= 300 && $response->status() < 400) {
            return $result + $this->failure($operationId, 'The catalogue sent a redirect, which this page does not follow.');
        }

        $presented = $this->present($response);
        $payload = [
            'ok' => true,
            'status' => $response->status(),
            'url' => $built['url'],
            'content_type' => $presented['content_type'],
            'body' => $presented['body'],
            'truncated' => $presented['truncated'],
        ];
        if ($response->successful()) {
            Cache::put($cacheKey, $payload, 600);
        }

        return $result + $payload;
    }

    /**
     * Values used for the visible curl/JS/Python example. Required fields and
     * a small limit only — optional filters stay empty until someone sets them.
     *
     * @param  array<string, mixed>  $operation
     * @return array<string, string>
     */
    public function exampleInput(array $operation): array
    {
        $input = [];
        $path = (string) ($operation['path'] ?? '');
        foreach ($operation['parameters'] ?? [] as $parameter) {
            if (! is_array($parameter)) {
                continue;
            }
            $name = (string) ($parameter['name'] ?? '');
            if ($name === '') {
                continue;
            }
            if ($name === 'limit') {
                $default = is_numeric($parameter['default'] ?? null) ? (int) $parameter['default'] : self::LIMIT_CAP;
                $maximum = is_numeric($parameter['maximum'] ?? null) ? (int) $parameter['maximum'] : self::LIMIT_CAP;
                $input[$name] = (string) max(1, min(self::LIMIT_CAP, $default, $maximum));

                continue;
            }
            if (($parameter['required'] ?? false) !== true) {
                continue;
            }
            $sample = $this->sample($path, $name);
            if ($sample !== null) {
                $input[$name] = $sample;
            }
        }

        return $input;
    }

    /**
     * @param  array<string, mixed>  $operation
     * @param  array<string, string>  $input
     * @return array{url: ?string, error: ?string, sent: array<string, string>}
     */
    public function build(string $origin, array $operation, array $input): array
    {
        $origin = rtrim($origin, '/');
        $originParts = parse_url($origin);
        if (! is_array($originParts) || ! in_array($originParts['scheme'] ?? '', ['http', 'https'], true) || ! isset($originParts['host'])) {
            return ['url' => null, 'error' => 'The catalogue address is not configured.', 'sent' => []];
        }

        $path = (string) ($operation['path'] ?? '');
        if (! str_starts_with($path, '/') || str_contains($path, '..') || str_contains($path, '\\') || str_contains($path, '://')) {
            return ['url' => null, 'error' => 'That operation is not published as a read-only GET.', 'sent' => []];
        }

        $query = [];
        $sent = [];
        foreach ($operation['parameters'] ?? [] as $parameter) {
            if (! is_array($parameter)) {
                continue;
            }
            $name = (string) ($parameter['name'] ?? '');
            $location = (string) ($parameter['in'] ?? '');
            if ($name === '' || ! in_array($location, ['path', 'query'], true)) {
                continue;
            }
            $raw = $input[$name] ?? '';
            if ($raw === '') {
                if (($parameter['required'] ?? false) === true) {
                    return ['url' => null, 'error' => "Add a value for {$name}.", 'sent' => []];
                }

                continue;
            }
            $value = $this->coerce($parameter, $raw);
            if ($value === null) {
                return ['url' => null, 'error' => $location === 'path'
                    ? 'That value cannot be used in the path.'
                    : "That value cannot be used for {$name}.", 'sent' => []];
            }
            $sent[$name] = $value;
            if ($location === 'path') {
                $path = str_replace('{'.$name.'}', rawurlencode($value), $path);

                continue;
            }
            $query[$name] = $value;
        }

        if (preg_match('/\{[^}]+\}/', $path) === 1 || str_contains($path, '..') || str_contains($path, '//')) {
            return ['url' => null, 'error' => 'The read-only request could not be built from those parameters.', 'sent' => []];
        }

        $url = $origin.$path;
        if ($query !== []) {
            $url .= '?'.http_build_query($query, '', '&', PHP_QUERY_RFC3986);
        }
        $parts = parse_url($url);
        if (! is_array($parts) || ($parts['scheme'] ?? '') !== $originParts['scheme'] || ($parts['host'] ?? '') !== $originParts['host']) {
            return ['url' => null, 'error' => 'The read-only request could not be built from those parameters.', 'sent' => []];
        }
        if (! str_starts_with($url, $origin.'/')) {
            return ['url' => null, 'error' => 'The read-only request could not be built from those parameters.', 'sent' => []];
        }

        return ['url' => $url, 'error' => null, 'sent' => $sent];
    }

    public function send(string $url): Response
    {
        return Http::timeout($this->timeoutFor($url))
            ->connectTimeout(5)
            ->withOptions(['allow_redirects' => false])
            ->accept('application/json, text/plain;q=0.9, */*;q=0.1')
            ->get($url);
    }

    /**
     * Night planning regularly takes longer than a catalogue read. Give that
     * call the planner budget so try-it can show the response.
     */
    public function timeoutFor(string $url): int
    {
        $path = (string) (parse_url($url, PHP_URL_PATH) ?: '');
        $reads = $this->timeout();
        if (str_contains($path, '/observing/night')) {
            return max($reads, (int) config('services.solar.planner_timeout', 40));
        }

        return $reads;
    }

    /**
     * @return array{content_type: string, body: string, truncated: bool}
     */
    public function present(Response $response): array
    {
        $raw = $response->body();
        $truncated = strlen($raw) > 120000;
        if ($truncated) {
            $raw = substr($raw, 0, 120000);
        }
        if (! $truncated) {
            $decoded = json_decode($raw, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $pretty = json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
                if (is_string($pretty)) {
                    $raw = $pretty;
                }
            }
        }
        if (strlen($raw) > 3500) {
            $raw = substr($raw, 0, 3500);
            $truncated = true;
        }

        $type = strtolower(trim(strtok((string) $response->header('Content-Type'), ';') ?: 'text/plain'));

        return [
            'content_type' => $type === '' ? 'text/plain' : $type,
            'body' => $raw,
            'truncated' => $truncated,
        ];
    }

    /**
     * @param  array<string, mixed>  $parameter
     */
    private function coerce(array $parameter, string $raw): ?string
    {
        if (preg_match('/[\x00-\x1F\x7F]/', $raw) === 1) {
            return null;
        }
        $type = (string) ($parameter['type'] ?? 'string');
        if (($parameter['in'] ?? '') === 'path') {
            if (strlen($raw) > 80 || str_contains($raw, '/') || str_contains($raw, '\\') || str_contains($raw, '..')) {
                return null;
            }
            if (preg_match('/^[A-Za-z0-9._~:+ -]+$/', $raw) !== 1) {
                return null;
            }

            return $raw;
        }
        if (strlen($raw) > 200) {
            return null;
        }

        if ($type === 'boolean') {
            $normal = strtolower($raw);

            return in_array($normal, ['true', 'false'], true) ? $normal : null;
        }
        if ($type === 'integer') {
            if (preg_match('/^-?\d+$/', $raw) !== 1) {
                return null;
            }
            $number = (int) $raw;
            if (is_numeric($parameter['minimum'] ?? null) && $number < (int) $parameter['minimum']) {
                return null;
            }
            if (is_numeric($parameter['maximum'] ?? null) && $number > (int) $parameter['maximum']) {
                return null;
            }
            if (($parameter['name'] ?? '') === 'limit') {
                $cap = self::LIMIT_CAP;
                if (is_numeric($parameter['maximum'] ?? null)) {
                    $cap = min($cap, (int) $parameter['maximum']);
                }
                $number = min($number, $cap);
            }

            return (string) $number;
        }
        if ($type === 'number') {
            if (preg_match('/^-?\d+(\.\d+)?$/', $raw) !== 1) {
                return null;
            }
            $number = (float) $raw;
            if (is_numeric($parameter['minimum'] ?? null) && $number < (float) $parameter['minimum']) {
                return null;
            }
            if (is_numeric($parameter['maximum'] ?? null) && $number > (float) $parameter['maximum']) {
                return null;
            }

            return $this->plainNumber($raw);
        }

        $minimumLength = is_numeric($parameter['min_length'] ?? null) ? (int) $parameter['min_length'] : 0;
        $maximumLength = is_numeric($parameter['max_length'] ?? null) ? (int) $parameter['max_length'] : 200;
        $length = strlen($raw);
        if ($length < $minimumLength || $length > min(200, $maximumLength)) {
            return null;
        }

        return $raw;
    }

    private function plainNumber(string $raw): string
    {
        if (str_contains($raw, '.')) {
            $raw = rtrim(rtrim($raw, '0'), '.');
        }

        return $raw === '' || $raw === '-' ? '0' : $raw;
    }

    private function sample(string $path, string $name): ?string
    {
        return match (true) {
            $name === 'name_or_designation' => 'planet-saturn',
            $name === 'code' => 'GEM',
            $name === 'target_id' => 'bsc5p:hr1017',
            $name === 'q' => 'Halley',
            $name === 'name' && str_contains($path, '/exoplanet-hosts/') => 'Proxima Cen',
            $name === 'name' && str_contains($path, '/exoplanets/') => 'Proxima Cen b',
            $name === 'name' => 'Saturn',
            $name === 'date' => '2026-10-10',
            $name === 'timezone' => 'UTC',
            $name === 'lat' => '51.5',
            $name === 'lon' => '-0.1',
            $name === 'from' => '2026-10-01',
            $name === 'to' => '2026-10-31',
            default => null,
        };
    }

    /**
     * @return array<string, string>
     */
    private function stringMap(mixed $input): array
    {
        if (! is_array($input)) {
            return [];
        }
        $map = [];
        foreach ($input as $key => $value) {
            if (! is_string($key) || ! is_scalar($value)) {
                continue;
            }
            $map[$key] = trim((string) $value);
        }

        return $map;
    }

    /**
     * @return array{ok: false, id: string, message: string}
     */
    private function failure(string $operationId, string $message): array
    {
        return ['ok' => false, 'id' => $operationId, 'message' => $message];
    }

    private function timeout(): int
    {
        return max(3, (int) config('services.solar.timeout', 8));
    }
}
