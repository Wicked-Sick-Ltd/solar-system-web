<?php

declare(strict_types=1);

namespace App\Services\SolarApi;

use App\Services\SolarApi\Data\CatalogueIdentity;
use Illuminate\Cache\Lock;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

/** Short shared observation lease. Never pins a separate scientific response. */
final class CatalogueContext
{
    public const int PROBE_SECONDS = 60;

    public const int UNKNOWN_DATA_SECONDS = 60;

    private const int MAX_BYTES = 262144;

    public static function storageKey(): string
    {
        return 'catalogue-observation:v1:'.hash('sha256', rtrim((string) config('services.solar.base_url'), '/'));
    }

    /** @return array{identity:CatalogueIdentity,token:string,checked:int} */
    public function current(): array
    {
        $key = self::storageKey();
        $previous = $this->readState($key);
        if (is_array($previous) && now()->timestamp < $previous['checked'] + self::PROBE_SECONDS) {
            return $previous;
        }
        $lock = Cache::lock($key.':probe', 5);
        if (! $lock->get()) {
            // Another worker is checking an expired observation. Do not serve
            // a prior known generation or write scientific cache entries.
            return ['identity' => new CatalogueIdentity('unavailable'), 'token' => 'checking', 'checked' => now()->timestamp];
        }
        $deadline = now()->timestamp + 5;
        try {
            $latest = $this->readState($key);
            if (is_array($latest) && now()->timestamp < $latest['checked'] + self::PROBE_SECONDS) {
                return $latest;
            }
            $identity = $this->probe();
            // A paused producer may outlive its lock despite the HTTP timeout.
            // Never publish over a newer producer after losing the lease.
            $after = $this->readState($key);
            if (! $lock instanceof Lock || ! $lock->isOwnedByCurrentProcess() || now()->timestamp >= $deadline
                || ($after !== null && ($latest === null || $after['token'] !== $latest['token'] || $after['checked'] !== $latest['checked']))) {
                return $after !== null && now()->timestamp < $after['checked'] + self::PROBE_SECONDS
                    ? $after
                    : ['identity' => new CatalogueIdentity('unavailable'), 'token' => 'checking', 'checked' => now()->timestamp];
            }
            $same = is_array($latest) && $latest['identity']->status === $identity->status
                && $latest['identity']->buildIdentifier === $identity->buildIdentifier
                && $latest['identity']->catalogueId === $identity->catalogueId;
            $state = ['identity' => $identity, 'token' => $same ? $latest['token'] : bin2hex(random_bytes(16)), 'checked' => now()->timestamp];
            Cache::put($key, array_replace($state, ['identity' => $identity->toArray()]), 86400);

            return $state;
        } finally {
            $lock->release();
        }
    }

    /**
     * @param  array<string,mixed>  $query
     * @param  array{identity:CatalogueIdentity,token:string,checked:int}|null  $state
     */
    public function key(string $path, array $query, ?array $state = null): string
    {
        $state ??= $this->current();
        ksort($query);

        return 'solar:v3:'.hash('sha256', self::storageKey().':'.$state['token'].':'.$path.'?'.http_build_query($query));
    }

    /** @return array<mixed>|null */
    public function metadata(string $path): ?array
    {
        try {
            $response = Http::acceptJson()->withHeaders(['Accept-Encoding' => 'identity'])->timeout(2)->connectTimeout(2)
                ->withOptions([
                    'allow_redirects' => false,
                    'decode_content' => false,
                    'on_headers' => static function ($response): void {
                        if ((int) $response->getHeaderLine('Content-Length') > self::MAX_BYTES
                            || ! in_array(strtolower($response->getHeaderLine('Content-Encoding')), ['', 'identity'], true)) {
                            throw new RuntimeException('Metadata too large.');
                        }
                    },
                    'progress' => static function ($total, $downloaded): void {
                        if ($downloaded > self::MAX_BYTES) {
                            throw new RuntimeException('Metadata too large.');
                        }
                    },
                ])->get(rtrim((string) config('services.solar.base_url'), '/').$path);
            if (! $response->successful() || strlen($response->body()) > self::MAX_BYTES
                || ! in_array(strtolower($response->header('Content-Encoding')), ['', 'identity'], true)) {
                return null;
            }
            $value = $response->json();

            return is_array($value) ? $value : null;
        } catch (Throwable) {
            return null;
        }
    }

    /** @return array{identity:CatalogueIdentity,token:string,checked:int}|null */
    private function readState(string $key): ?array
    {
        $state = Cache::get($key);
        if (! is_array($state) || ! is_array($state['identity'] ?? null)
            || ! is_string($state['token'] ?? null) || ! is_int($state['checked'] ?? null)) {
            return null;
        }

        return ['identity' => CatalogueIdentity::fromArray($state['identity']), 'token' => $state['token'], 'checked' => $state['checked']];
    }

    private function probe(): CatalogueIdentity
    {
        // Legacy 404s, failures and malformed envelopes share a short lease;
        // none causes a second probe for every item on a page.
        return CatalogueIdentity::fromArray($this->metadata('/catalogue'));
    }
}
