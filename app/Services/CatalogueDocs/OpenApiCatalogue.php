<?php

declare(strict_types=1);

namespace App\Services\CatalogueDocs;

use App\Support\Links;
use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Public GET reference built from the catalogue's live OpenAPI document.
 * Write operations and anything that requires a header or cookie are omitted.
 */
final class OpenApiCatalogue
{
    public function __construct(private CatalogueCall $calls) {}

    /**
     * @return array<string, mixed>|null
     */
    public function document(): ?array
    {
        $url = Links::openApi();
        $key = 'catalogue-docs:openapi:v2:'.hash('sha256', $url);
        $cached = Cache::get($key);
        if (is_array($cached) && isset($cached['operations'])) {
            return $cached;
        }

        $lock = Cache::lock($key.':lock', 25);
        $locked = false;
        try {
            $locked = $lock->block(20) === true;
        } catch (Throwable) {
            $locked = false;
        }

        try {
            $cached = Cache::get($key);
            if (is_array($cached) && isset($cached['operations'])) {
                return $cached;
            }
            $document = $this->fetch($url);
            if ($document === null) {
                $stale = Cache::get($key.':stale');

                return is_array($stale) ? $stale : null;
            }
            Cache::put($key, $document, $this->ttl());
            Cache::put($key.':stale', $document, $this->ttl() * 14);

            return $document;
        } finally {
            if ($locked) {
                $lock->release();
            }
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    private function fetch(string $url): ?array
    {
        try {
            $response = Http::timeout($this->timeout())
                ->connectTimeout(5)
                ->accept('application/json')
                ->get($url);
        } catch (Throwable $exception) {
            Log::warning('OpenAPI document unavailable', ['message' => $exception->getMessage()]);

            return null;
        }
        if (! $response->successful() || strlen($response->body()) > 2_000_000) {
            return null;
        }
        $decoded = json_decode($response->body(), true);
        if (! is_array($decoded)) {
            return null;
        }
        $document = $this->parse($decoded, $this->origin());
        if ($document === null) {
            return null;
        }
        $document['operations'] = $this->withExamples($document['operations'], (string) $document['origin']);

        return $document;
    }

    /**
     * @param  array<string, mixed>  $spec
     * @return array<string, mixed>|null
     */
    public function parse(array $spec, string $origin): ?array
    {
        $version = (string) ($spec['openapi'] ?? '');
        if (! str_starts_with($version, '3.') || ! is_array($spec['paths'] ?? null)) {
            return null;
        }
        $info = is_array($spec['info'] ?? null) ? $spec['info'] : [];
        $operations = [];
        $seen = [];
        foreach ($spec['paths'] as $path => $item) {
            if (! is_string($path) || ! str_starts_with($path, '/') || ! is_array($item)) {
                continue;
            }
            $operation = $item['get'] ?? null;
            if (! is_array($operation)) {
                continue;
            }
            if ($this->requiresCredential($operation, $spec)) {
                continue;
            }
            $normalised = $this->operation($path, $operation, $spec);
            if ($normalised === null) {
                continue;
            }
            $id = $normalised['id'];
            if (isset($seen[$id])) {
                $id .= '-'.count($seen);
                $normalised['id'] = $id;
                $normalised['html_id'] = 'op-'.$id;
            }
            $seen[$id] = true;
            $operations[] = $normalised;
            if (count($operations) >= 80) {
                break;
            }
        }

        return [
            'title' => $this->text($info['title'] ?? 'Catalogue API', 200),
            'version' => $this->text($info['version'] ?? '', 40),
            'description' => $this->text($info['description'] ?? '', 4000),
            'contact_name' => $this->httpsName($info['contact'] ?? null),
            'contact_url' => $this->httpsUrl($info['contact'] ?? null),
            'license_name' => $this->httpsName($info['license'] ?? null),
            'license_url' => $this->httpsUrl($info['license'] ?? null),
            'origin' => rtrim($origin, '/'),
            'operations' => $operations,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $operations
     * @return list<array<string, mixed>>
     */
    private function withExamples(array $operations, string $origin): array
    {
        $urls = [];
        foreach ($operations as $index => $operation) {
            $input = $this->calls->exampleInput($operation);
            $operation['example_input'] = $input;
            $built = $this->calls->build($origin, $operation, $input);
            $operation['example_url'] = $built['url'];
            $operation['catalogue_url'] = $built['url'] ?? ($origin.$operation['path']);
            $operation['snippets'] = $built['url'] === null ? null : [
                'curl' => Snippets::curl($built['url']),
                'javascript' => Snippets::javascript($built['url'], (bool) $operation['json']),
                'python' => Snippets::python($built['url'], (bool) $operation['json']),
            ];
            $operation['example'] = null;
            $operations[$index] = $operation;
            if ($built['url'] !== null && ! $this->skipExample($operation['path'])) {
                $urls[$operation['id']] = $built['url'];
            }
        }
        if ($urls === []) {
            return $operations;
        }

        try {
            $responses = Http::pool(function (Pool $pool) use ($urls): void {
                foreach ($urls as $id => $url) {
                    $pool->as($id)
                        ->timeout($this->timeout())
                        ->connectTimeout(5)
                        ->withOptions(['allow_redirects' => false])
                        ->accept('application/json, text/plain;q=0.9, */*;q=0.1')
                        ->get($url);
                }
            });
        } catch (Throwable $exception) {
            Log::warning('OpenAPI example responses unavailable', ['message' => $exception->getMessage()]);

            return $operations;
        }

        foreach ($operations as $index => $operation) {
            $response = $responses[$operation['id']] ?? null;
            if (! $response instanceof Response || ! $response->successful() || $response->status() >= 300) {
                continue;
            }
            $operations[$index]['example'] = $this->calls->present($response);
        }

        return $operations;
    }

    private function skipExample(string $path): bool
    {
        return str_contains($path, '/observing/night') || str_contains($path, '/close-approaches');
    }

    /**
     * @param  array<string, mixed>  $operation
     * @param  array<string, mixed>  $spec
     * @return array<string, mixed>|null
     */
    private function operation(string $path, array $operation, array $spec): ?array
    {
        $parameters = [];
        foreach ($this->parameterList($operation, $spec) as $parameter) {
            if (in_array($parameter['in'], ['header', 'cookie'], true) && $parameter['required']) {
                return null;
            }
            if (! in_array($parameter['in'], ['path', 'query'], true)) {
                continue;
            }
            $parameters[] = $parameter;
            if (count($parameters) >= 40) {
                break;
            }
        }

        $id = (string) ($operation['operationId'] ?? '');
        if (preg_match('/^[A-Za-z0-9_.-]{1,120}$/', $id) !== 1) {
            $id = 'get-'.substr(hash('sha256', $path), 0, 12);
        }
        $content = $operation['responses']['200']['content'] ?? [];
        $json = ! is_array($content) || $content === [] || array_key_exists('application/json', $content);
        $tags = $operation['tags'] ?? [];
        $tag = is_array($tags) && is_string($tags[0] ?? null) && trim($tags[0]) !== ''
            ? $this->text($tags[0], 80)
            : 'Catalogue';

        return [
            'id' => $id,
            'html_id' => 'op-'.$id,
            'path' => $path,
            'summary' => $this->text($operation['summary'] ?? $path, 200),
            'description' => $this->text($operation['description'] ?? '', 2000),
            'tag' => $tag,
            'deprecated' => ($operation['deprecated'] ?? false) === true,
            'json' => $json,
            'parameters' => $parameters,
        ];
    }

    /**
     * @param  array<string, mixed>  $operation
     * @param  array<string, mixed>  $spec
     * @return list<array<string, mixed>>
     */
    private function parameterList(array $operation, array $spec): array
    {
        $parameters = [];
        $sources = $operation['parameters'] ?? [];
        if (! is_array($sources)) {
            return [];
        }
        foreach ($sources as $parameter) {
            if (is_array($parameter) && isset($parameter['$ref']) && is_string($parameter['$ref'])) {
                $parameter = $this->resolveRef($parameter['$ref'], $spec);
            }
            if (! is_array($parameter)) {
                continue;
            }
            $normalised = $this->parameter($parameter);
            if ($normalised !== null) {
                $parameters[] = $normalised;
            }
        }

        return $parameters;
    }

    /**
     * @param  array<array-key, mixed>  $spec
     * @return array<string, mixed>|null
     */
    private function resolveRef(string $ref, array $spec): ?array
    {
        if (! str_starts_with($ref, '#/')) {
            return null;
        }
        $cursor = $spec;
        foreach (explode('/', substr($ref, 2)) as $part) {
            $part = str_replace('~1', '/', str_replace('~0', '~', $part));
            if (! is_array($cursor) || ! array_key_exists($part, $cursor)) {
                return null;
            }
            $cursor = $cursor[$part];
        }

        return is_array($cursor) ? $cursor : null;
    }

    /**
     * @param  array<string, mixed>  $parameter
     * @return array<string, mixed>|null
     */
    private function parameter(array $parameter): ?array
    {
        $name = $parameter['name'] ?? '';
        $in = $parameter['in'] ?? '';
        if (! is_string($name) || preg_match('/^[A-Za-z_][A-Za-z0-9_-]{0,63}$/', $name) !== 1) {
            return null;
        }
        if (! is_string($in) || ! in_array($in, ['path', 'query', 'header', 'cookie'], true)) {
            return null;
        }
        $schema = is_array($parameter['schema'] ?? null) ? $parameter['schema'] : [];
        $shape = $this->schemaShape($schema);
        $description = $parameter['description'] ?? $shape['description'] ?? '';
        $constraints = [];
        if ($shape['default'] !== null) {
            $constraints[] = 'default '.$shape['default'];
        }
        if ($shape['minimum'] !== null) {
            $constraints[] = 'minimum '.$shape['minimum'];
        }
        if ($shape['maximum'] !== null) {
            $constraints[] = 'maximum '.$shape['maximum'];
        }

        return [
            'name' => $name,
            'in' => $in,
            'required' => ($parameter['required'] ?? false) === true || $in === 'path',
            'type' => $shape['type'],
            'description' => $this->text(is_string($description) ? $description : '', 400),
            'default' => $shape['default'],
            'minimum' => $shape['minimum'],
            'maximum' => $shape['maximum'],
            'min_length' => $shape['min_length'],
            'max_length' => $shape['max_length'],
            'constraints' => implode(', ', $constraints),
        ];
    }

    /**
     * @param  array<string, mixed>  $schema
     * @return array{type: string, description: ?string, default: ?string, minimum: ?string, maximum: ?string, min_length: ?int, max_length: ?int}
     */
    private function schemaShape(array $schema): array
    {
        $sources = [$schema];
        foreach ($schema['anyOf'] ?? [] as $branch) {
            if (is_array($branch) && ($branch['type'] ?? null) !== 'null') {
                $sources[] = $branch;
            }
        }
        $type = 'string';
        $description = null;
        $default = null;
        $minimum = null;
        $maximum = null;
        $minLength = null;
        $maxLength = null;
        foreach ($sources as $source) {
            if (is_string($source['type'] ?? null) && in_array($source['type'], ['string', 'integer', 'number', 'boolean'], true)) {
                $type = $source['type'];
            }
            if (is_string($source['description'] ?? null)) {
                $description = $source['description'];
            }
            if (array_key_exists('default', $source) && (is_scalar($source['default']) || $source['default'] === null)) {
                $default = $source['default'] === null ? null : $this->scalarString($source['default']);
            }
            if (is_numeric($source['minimum'] ?? null)) {
                $minimum = $this->scalarString($source['minimum']);
            }
            if (is_numeric($source['maximum'] ?? null)) {
                $maximum = $this->scalarString($source['maximum']);
            }
            if (is_int($source['minLength'] ?? null)) {
                $minLength = $source['minLength'];
            }
            if (is_int($source['maxLength'] ?? null)) {
                $maxLength = $source['maxLength'];
            }
        }

        return [
            'type' => $type,
            'description' => $description,
            'default' => $default,
            'minimum' => $minimum,
            'maximum' => $maximum,
            'min_length' => $minLength,
            'max_length' => $maxLength,
        ];
    }

    /**
     * @param  array<string, mixed>  $operation
     * @param  array<string, mixed>  $spec
     */
    private function requiresCredential(array $operation, array $spec): bool
    {
        $security = $operation['security'] ?? $spec['security'] ?? [];

        return is_array($security) && $security !== [];
    }

    private function httpsName(mixed $block): ?string
    {
        if (! is_array($block) || ! is_string($block['name'] ?? null)) {
            return null;
        }
        $name = trim($block['name']);

        return $name === '' ? null : $this->text($name, 120);
    }

    private function httpsUrl(mixed $block): ?string
    {
        if (! is_array($block) || ! is_string($block['url'] ?? null)) {
            return null;
        }
        $url = trim($block['url']);
        $parts = parse_url($url);

        return is_array($parts) && ($parts['scheme'] ?? '') === 'https' && isset($parts['host']) ? $url : null;
    }

    private function scalarString(mixed $value): string
    {
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        return trim((string) $value);
    }

    private function text(mixed $value, int $limit): string
    {
        $text = trim(preg_replace('/\s+/', ' ', is_string($value) ? $value : '') ?? '');
        if (strlen($text) > $limit) {
            return substr($text, 0, $limit).'…';
        }

        return $text;
    }

    private function origin(): string
    {
        return Links::catalogueOrigin();
    }

    private function timeout(): int
    {
        return max(3, (int) config('services.solar.timeout', 8));
    }

    private function ttl(): int
    {
        return max(60, (int) config('services.solar.cache.catalog', 21600));
    }
}
