<?php

declare(strict_types=1);

namespace App\Services\CatalogueDocs;

use App\Support\Links;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Read-only tools and resources reported by the live solar-mcp endpoint.
 * Tools without an explicit read-only annotation are omitted.
 */
final class McpCatalogue
{
    private const PROTOCOL = '2025-03-26';

    /** @var array<string, string> */
    private const PROMPTS = [
        'search' => 'Search the catalogue for Halley and cite each matching record.',
        'get_object' => 'Get the full catalogue record for Saturn and name the source of the physical measurements.',
        'list_moons' => "List Saturn's moons in orbital-distance order.",
        'get_meteor_shower' => 'Describe the Geminids from the meteor-shower catalogue, including the parent body.',
        'get_sky_position' => 'Where does Saturn appear in the sky from latitude 51.5 and longitude -0.1?',
        'get_stats' => 'How many objects are in the catalogue, and when was it last built?',
    ];

    /**
     * @return array<string, mixed>|null
     */
    public function document(): ?array
    {
        $url = Links::mcp();
        $key = 'catalogue-docs:mcp:v1:'.hash('sha256', $url);
        $cached = Cache::get($key);
        if (is_array($cached) && isset($cached['tools'])) {
            return $cached;
        }

        try {
            $document = $this->fetch($url);
        } catch (Throwable $exception) {
            Log::warning('MCP tool list unavailable', ['message' => $exception->getMessage()]);
            $document = null;
        }
        if ($document === null) {
            $stale = Cache::get($key.':stale');

            return is_array($stale) ? $stale : null;
        }
        Cache::put($key, $document, $this->ttl());
        Cache::put($key.':stale', $document, $this->ttl() * 14);

        return $document;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function fetch(string $url): ?array
    {
        $headers = [
            'Accept' => 'application/json, text/event-stream',
            'Content-Type' => 'application/json',
        ];
        $initial = $this->post($url, $headers, [
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => 'initialize',
            'params' => [
                'protocolVersion' => self::PROTOCOL,
                'capabilities' => (object) [],
                'clientInfo' => ['name' => 'public-universe-docs', 'version' => '1'],
            ],
        ]);
        if ($initial === null) {
            return null;
        }
        $session = (string) $initial->header('mcp-session-id');
        $payload = $this->rpc($initial->body());
        $result = is_array($payload['result'] ?? null) ? $payload['result'] : null;
        $info = is_array($result['serverInfo'] ?? null) ? $result['serverInfo'] : [];
        $name = $info['name'] ?? '';
        if (preg_match('/^[A-Za-z0-9-]{8,80}$/', $session) !== 1) {
            return null;
        }
        if (! is_string($name) || preg_match('/^[A-Za-z0-9_-]{1,64}$/', $name) !== 1) {
            return null;
        }

        $headers['mcp-session-id'] = $session;
        $headers['MCP-Protocol-Version'] = self::PROTOCOL;
        $ready = $this->post($url, $headers, ['jsonrpc' => '2.0', 'method' => 'notifications/initialized']);
        if ($ready === null || $ready->status() >= 400) {
            return null;
        }
        $tools = $this->post($url, $headers, ['jsonrpc' => '2.0', 'id' => 2, 'method' => 'tools/list', 'params' => (object) []]);
        $resources = $this->post($url, $headers, ['jsonrpc' => '2.0', 'id' => 3, 'method' => 'resources/list', 'params' => (object) []]);
        $toolPayload = $tools === null ? null : $this->rpc($tools->body());
        $listed = is_array($toolPayload['result']['tools'] ?? null) ? $toolPayload['result']['tools'] : null;
        if ($listed === null) {
            return null;
        }

        $published = [];
        foreach ($listed as $tool) {
            if (! is_array($tool)) {
                continue;
            }
            $safe = $this->tool($tool);
            if ($safe !== null) {
                $published[] = $safe;
            }
            if (count($published) >= 80) {
                break;
            }
        }
        $names = array_column($published, 'name');
        $prompts = [];
        foreach (self::PROMPTS as $toolName => $prompt) {
            if (in_array($toolName, $names, true)) {
                $prompts[] = $prompt;
            }
        }

        $version = $info['version'] ?? '';
        $instructions = is_string($result['instructions'] ?? null) ? trim($result['instructions']) : '';

        return [
            'name' => $name,
            'version' => is_string($version) && preg_match('/^[A-Za-z0-9._+-]{1,40}$/', $version) === 1 ? $version : null,
            'instructions' => $this->clip($instructions, 2000),
            'tools' => $published,
            'resources' => $this->resources($resources),
            'prompts' => $prompts,
            'config' => Snippets::mcp($name, $url),
        ];
    }

    /**
     * @param  array<string, string>  $headers
     * @param  array<string, mixed>  $body
     */
    private function post(string $url, array $headers, array $body): ?Response
    {
        try {
            $response = Http::timeout($this->timeout())
                ->connectTimeout(5)
                ->withHeaders($headers)
                ->withBody(json_encode($body, JSON_THROW_ON_ERROR), 'application/json')
                ->post($url);
        } catch (Throwable) {
            return null;
        }

        return $response->status() === 0 ? null : $response;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function rpc(string $body): ?array
    {
        if (strlen($body) > 1_000_000) {
            return null;
        }
        $body = trim($body);
        if ($body === '') {
            return null;
        }
        $json = str_starts_with($body, '{') ? $body : null;
        if ($json === null && preg_match('/^data: (\{.*\})\s*$/m', $body, $matches) === 1) {
            $json = $matches[1];
        }
        if ($json === null) {
            return null;
        }
        $decoded = json_decode($json, true);

        return is_array($decoded) ? $decoded : null;
    }

    /**
     * @param  array<string, mixed>  $tool
     * @return array<string, mixed>|null
     */
    private function tool(array $tool): ?array
    {
        $name = $tool['name'] ?? '';
        $annotations = $tool['annotations'] ?? null;
        if (! is_string($name) || preg_match('/^[A-Za-z0-9_-]{1,80}$/', $name) !== 1 || ! is_array($annotations)) {
            return null;
        }
        if (($annotations['readOnlyHint'] ?? false) !== true || ($annotations['destructiveHint'] ?? false) === true) {
            return null;
        }
        $description = is_string($tool['description'] ?? null) ? trim($tool['description']) : '';
        $schema = is_array($tool['inputSchema'] ?? null) ? $tool['inputSchema'] : [];
        $properties = is_array($schema['properties'] ?? null) ? $schema['properties'] : [];
        $required = is_array($schema['required'] ?? null) ? $schema['required'] : [];
        $parameters = [];
        foreach ($properties as $property => $definition) {
            if (! is_string($property) || preg_match('/^[A-Za-z_][A-Za-z0-9_]{0,63}$/', $property) !== 1) {
                continue;
            }
            $detail = is_array($definition) && is_string($definition['description'] ?? null) ? trim($definition['description']) : '';
            $parameters[] = [
                'name' => $property,
                'required' => in_array($property, $required, true),
                'description' => $this->clip($detail, 300),
            ];
            if (count($parameters) >= 40) {
                break;
            }
        }

        $summary = preg_split("/\r\n|\n|\r/", $description)[0] ?? $description;
        $summary = trim((string) $summary);
        if (strlen($summary) > 180) {
            $summary = substr($summary, 0, 180).'…';
        }

        return [
            'name' => $name,
            'summary' => $summary,
            'description' => $this->clip($description, 4000),
            'parameters' => $parameters,
        ];
    }

    /**
     * @return list<array{uri: string, name: string, description: string}>
     */
    private function resources(?Response $response): array
    {
        if ($response === null) {
            return [];
        }
        $payload = $this->rpc($response->body());
        $listed = $payload['result']['resources'] ?? null;
        if (! is_array($listed)) {
            return [];
        }
        $resources = [];
        foreach ($listed as $resource) {
            if (! is_array($resource) || ! is_string($resource['uri'] ?? null)) {
                continue;
            }
            $uri = trim($resource['uri']);
            if (preg_match('#^[a-z][a-z0-9+.-]*://[^\s]{1,180}$#', $uri) !== 1 || preg_match('#://[^/]*@#', $uri) === 1) {
                continue;
            }
            $resources[] = [
                'uri' => $uri,
                'name' => $this->clip(is_string($resource['name'] ?? null) ? $resource['name'] : '', 80),
                'description' => $this->clip(is_string($resource['description'] ?? null) ? $resource['description'] : '', 300),
            ];
            if (count($resources) >= 20) {
                break;
            }
        }

        return $resources;
    }

    private function clip(string $text, int $limit): string
    {
        $text = trim($text);
        if (strlen($text) <= $limit) {
            return $text;
        }

        return substr($text, 0, $limit).'…';
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
