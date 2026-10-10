<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Services\CatalogueDocs\CatalogueCall;
use App\Services\CatalogueDocs\McpCatalogue;
use App\Services\CatalogueDocs\OpenApiCatalogue;
use App\Services\CatalogueDocs\Snippets;
use App\Support\Links;
use App\Support\Seo;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.app')]
final class ApiPage extends Component
{
    public function render(): View
    {
        app(Seo::class)
            ->title(__('Catalogue API'))
            ->description(__('Public developer reference for the read-only astronomy catalogue API and its MCP server. Generated from the live OpenAPI document.'));

        $document = app(OpenApiCatalogue::class)->document();
        $server = app(McpCatalogue::class)->document();
        $mcpUrl = Links::mcp();
        $try = request()->query('try');
        $call = null;
        if (is_string($try) && $try !== '') {
            $params = request()->query('p', []);
            $call = app(CatalogueCall::class)->attempt($document, $try, $params);
        }

        $groups = [];
        $featured = null;
        foreach ($document['operations'] ?? [] as $operation) {
            if (! is_array($operation)) {
                continue;
            }
            $tag = (string) ($operation['tag'] ?? 'Catalogue');
            $groups[$tag][] = $operation;
            if ($featured === null && ($operation['path'] ?? '') === '/api/v1/search') {
                $featured = $operation;
            }
        }

        $toolNames = array_column(is_array($server['tools'] ?? null) ? $server['tools'] : [], 'name');

        return view('livewire.api-page', [
            'baseUrl' => (string) config('services.solar.base_url'),
            'mcpUrl' => $mcpUrl,
            'docsUrl' => Links::apiDocs(),
            'openApiUrl' => Links::openApi(),
            'catalogue' => $document,
            'groups' => $groups,
            'featured' => $featured,
            'server' => $server,
            'mcpConfig' => is_array($server['config'] ?? null) ? $server['config'] : Snippets::mcp('solar-mcp', $mcpUrl),
            'mcpName' => is_string($server['name'] ?? null) ? $server['name'] : 'solar-mcp',
            'call' => $call,
            'plannerNight' => in_array('plan_observing_night', $toolNames, true),
            'plannerDiscover' => in_array('discover_observing_targets', $toolNames, true),
            'tagIds' => collect($groups)->mapWithKeys(fn (array $operations, string $tag): array => [
                $tag => 'tag-'.Str::slug($tag),
            ])->all(),
        ]);
    }
}
