<?php

declare(strict_types=1);

namespace App\Services\SolarApi;

use App\Services\SolarApi\Data\StarterPage;
use App\Services\SolarApi\Data\StarterSource;
use App\Services\SolarApi\Data\StarterTarget;
use App\Services\SolarApi\Exceptions\SolarApiException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

final class StarterCatalogueClient
{
    public const int PER_PAGE = 24;

    public function catalogue(string $q = '', string $family = '', int $page = 1): StarterPage
    {
        return $this->cached('list', [$q, $family, $page], fn () => $this->fetchCatalogue($q, $family, $page));
    }

    private function fetchCatalogue(string $q, string $family, int $page): StarterPage
    {
        $query = ['q' => $q, 'limit' => self::PER_PAGE, 'offset' => ($page - 1) * self::PER_PAGE];
        if ($family !== '') {
            $query['family'] = $family;
        }
        $data = $this->get('/starter-targets', $query);
        if (! is_array($data) || ($data['available'] ?? null) !== true
            || ! is_array($data['results'] ?? null) || ! array_is_list($data['results'])
            || ! is_int($data['total'] ?? null) || $data['total'] < 0 || $data['total'] > 1000
            || ($data['limit'] ?? null) !== self::PER_PAGE || ($data['offset'] ?? null) !== $query['offset']
            || count($data['results']) !== min(self::PER_PAGE, max(0, $data['total'] - $query['offset']))
            || ! is_array($data['sources'] ?? null)) {
            throw new SolarApiException('The observing starter catalogue is unavailable.');
        }
        $sources = [];
        foreach (['bsc5p', 'openngc'] as $source) {
            $sources[$source] = StarterSource::fromArray($data['sources'][$source] ?? null, $source);
        }
        $targets = [];
        $ids = [];
        foreach ($data['results'] as $row) {
            if (! is_array($row) || ! is_string($row['source'] ?? null) || ! isset($sources[$row['source']])) {
                throw new SolarApiException('The starter record has no source.');
            }
            $target = StarterTarget::fromArray($row, $sources[$row['source']]);
            if (isset($ids[$target->id]) || ($family !== '' && ! in_array($family, $target->families, true))) {
                throw new SolarApiException('Inconsistent starter catalogue selection.');
            }
            $ids[$target->id] = true;
            $targets[] = $target;
        }

        return new StarterPage($targets, $data['total'], $sources);
    }

    public function target(string $id): ?StarterTarget
    {
        return $this->cached('detail', [$id], fn () => $this->fetchTarget($id));
    }

    private function fetchTarget(string $id): ?StarterTarget
    {
        $data = $this->get('/starter-targets/'.rawurlencode($id));
        if ($data === null) {
            $this->catalogue(); // Distinguish absent identity from unavailable backend schema.

            return null;
        }
        if (! is_array($data) || ($data['id'] ?? null) !== $id || ! is_string($data['source'] ?? null)) {
            throw new SolarApiException('Inconsistent starter target response.');
        }

        return StarterTarget::fromArray($data, StarterSource::fromArray($data['provenance'] ?? null, $data['source']));
    }

    /**
     * @param  list<string|int>  $input
     * @param  callable(): (StarterPage|StarterTarget|null)  $fetch
     */
    private function cached(string $operation, array $input, callable $fetch): mixed
    {
        $context = app(CatalogueContext::class);
        $state = $context->current();
        $path = 'starter:v3:'.$operation;
        $query = ['input' => $input];
        $key = $context->key($path, $query, $state);
        $entry = $state['token'] === 'checking' ? null : Cache::get($key);
        if (is_array($entry) && array_key_exists('value', $entry)) {
            return $this->restore($entry['value']);
        }
        $value = $fetch();
        if ($state['token'] !== 'checking' && $context->key($path, $query) === $key) {
            Cache::put($key, ['value' => $this->serialize($value)], $state['identity']->known() ? 3600 : CatalogueContext::UNKNOWN_DATA_SECONDS);
        }

        return $value;
    }

    /** @return array<string,mixed> */
    private function serialize(StarterPage|StarterTarget|null $value): array
    {
        if ($value instanceof StarterPage) {
            return ['total' => $value->total,
                'targets' => array_map(static fn (StarterTarget $target) => $target->data, $value->targets),
                'sources' => array_map(static fn (StarterSource $source) => $source->data, $value->sources)];
        }

        return ['target' => $value?->data, 'source' => $value?->provenance->data];
    }

    /** @param array<string,mixed> $value */
    private function restore(array $value): StarterPage|StarterTarget|null
    {
        if (isset($value['targets'], $value['sources'], $value['total'])) {
            $sources = [];
            foreach ($value['sources'] as $name => $source) {
                $sources[$name] = StarterSource::fromArray($source, $name);
            }
            $targets = array_map(static fn ($row) => StarterTarget::fromArray($row, $sources[$row['source']]), $value['targets']);

            return new StarterPage($targets, $value['total'], $sources);
        }
        if ($value['target'] === null) {
            return null;
        }

        return StarterTarget::fromArray($value['target'], StarterSource::fromArray($value['source'], $value['target']['source']));
    }

    /** @param array<string,string|int> $query */
    private function get(string $path, array $query = []): mixed
    {
        $base = rtrim((string) config('services.solar.base_url'), '/');
        try {
            $response = Http::acceptJson()->timeout((int) config('services.solar.timeout', 8))
                ->withOptions(['allow_redirects' => false])->get($base.$path, $query);
        } catch (ConnectionException $exception) {
            throw new SolarApiException('The starter catalogue could not be reached.', previous: $exception);
        }
        if ($response->status() === 404) {
            return null;
        }
        if (! $response->successful() || ! is_array($response->json())) {
            throw new SolarApiException('The starter catalogue response is unavailable.');
        }

        return $response->json();
    }
}
