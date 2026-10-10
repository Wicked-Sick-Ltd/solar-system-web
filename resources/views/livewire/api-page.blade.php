<div class="mx-auto max-w-4xl">
    <x-page-header :title="__('Catalogue API')" :eyebrow="__('For developers')"
        :lead="__('A free, read-only HTTP catalogue of solar-system and exoplanet data, plus a streamable HTTP MCP server. No API key and no account.')" />

    <x-section-navigation :sections="[
        ['id' => 'overview', 'label' => __('Overview')],
        ['id' => 'endpoints', 'label' => __('Endpoints')],
        ['id' => 'limits', 'label' => __('Limits and attribution')],
        ['id' => 'mcp', 'label' => __('MCP server')],
    ]" />

    <div class="space-y-8 leading-relaxed">
        <section class="surface p-5 sm:p-6" aria-labelledby="overview">
            <h2 id="overview" tabindex="-1" class="font-serif text-2xl">{{ __('Overview') }}</h2>
            @if (is_array($catalogue) && $catalogue['description'] !== '')
                <div class="mt-3">{!! \App\Services\CatalogueDocs\InlineText::html($catalogue['description']) !!}</div>
            @else
                <p class="mt-3">{{ __('The catalogue publishes planets, moons, dwarf planets, asteroids, comets and related observing data for astronomy and science education.') }}</p>
            @endif
            <p class="mt-3">{{ __('This reference lists the public GET operations from the live OpenAPI document and caches that document on the server. Calls you run from the forms below are read-only GETs made by this site.') }}</p>
            <dl class="mt-4 grid gap-3 text-sm sm:grid-cols-[auto_1fr] sm:gap-x-5">
                <dt class="font-semibold">{{ __('Base URL') }}</dt>
                <dd class="min-w-0 break-all font-mono">{{ $baseUrl }}</dd>
                @if (is_array($catalogue) && $catalogue['version'] !== '')
                    <dt class="font-semibold">{{ __('Specification') }}</dt>
                    <dd>{{ $catalogue['title'] }} {{ $catalogue['version'] }}</dd>
                @endif
                <dt class="font-semibold">{{ __('MCP') }}</dt>
                <dd class="min-w-0 break-all">solar-mcp · <span class="font-mono">{{ $mcpUrl }}</span></dd>
            </dl>
            <x-code-block id="base-url" :label="__('Catalogue base URL')">{{ $baseUrl }}</x-code-block>
            @if (is_array($featured) && is_array($featured['snippets'] ?? null))
                <h3 class="mt-6 text-xl">{{ __('A worked example') }}</h3>
                <p class="mt-2 text-sm" style="color: var(--muted);">{{ $featured['summary'] }}</p>
                <x-code-block id="featured-curl" :label="__('curl example for :summary', ['summary' => $featured['summary']])">{{ $featured['snippets']['curl'] }}</x-code-block>
            @endif
            <div class="mt-4 flex flex-wrap gap-3">
                <a href="{{ $docsUrl }}" rel="noopener" target="_blank" class="inline-flex min-h-11 items-center rounded-lg px-4 text-sm font-semibold" style="background-color: var(--accent); color: #07090f;">{{ __('Interactive docs ↗') }}</a>
                <a href="{{ $openApiUrl }}" rel="noopener" target="_blank" class="inline-flex min-h-11 items-center rounded-lg border px-4 text-sm font-medium" style="border-color: var(--border); color: var(--text);">{{ __('OpenAPI JSON ↗') }}</a>
            </div>
        </section>

        <section aria-labelledby="endpoints">
            <h2 id="endpoints" tabindex="-1" class="font-serif text-2xl">{{ __('Endpoint reference') }}</h2>
            @if (is_array($call) && data_get($call, 'ok') !== true)
                @php
                    $matched = collect(data_get($catalogue, 'operations', []))->contains(fn ($operation) => is_array($operation) && ($operation['id'] ?? null) === data_get($call, 'id'));
                @endphp
                @unless ($matched)
                    <p class="mt-3" role="alert">{{ data_get($call, 'message') }}</p>
                @endunless
            @endif
            @if (! is_array($catalogue))
                <p class="mt-3" role="status">{{ __('The endpoint reference is unavailable because the OpenAPI document could not be read. The links above still point at the live document.') }}</p>
            @else
                <p class="mt-3 text-sm" style="color: var(--muted);">{{ __('Paths come from the cached OpenAPI document. Example snippets use a limit of at most 3 when the operation has a limit parameter. Interactive calls use the same cap so a response fits on the page; a direct request can use the maximum printed on the operation.') }}</p>
                <nav aria-label="{{ __('Endpoints') }}" class="mt-4">
                    <ul class="space-y-4">
                        @foreach ($groups as $tag => $operations)
                            <li>
                                <a class="font-semibold underline" href="#{{ $tagIds[$tag] }}">{{ $tag }}</a>
                                <ul class="mt-1 space-y-1">
                                    @foreach ($operations as $operation)
                                        <li class="min-w-0">
                                            <a class="inline-flex min-h-11 items-center break-all underline" href="#{{ $operation['html_id'] }}">
                                                <span class="mr-2 font-mono text-xs">GET</span>{{ $operation['path'] }}
                                            </a>
                                        </li>
                                    @endforeach
                                </ul>
                            </li>
                        @endforeach
                    </ul>
                </nav>

                @foreach ($groups as $tag => $operations)
                    <h3 id="{{ $tagIds[$tag] }}" tabindex="-1" class="mt-8 font-serif text-2xl">{{ $tag }}</h3>
                    <div class="mt-4 space-y-4">
                        @foreach ($operations as $operation)
                            <details id="{{ $operation['html_id'] }}" class="surface scroll-mt-24 p-4 sm:p-5" @if (data_get($call, 'id') === $operation['id'] || $operation['path'] === '/api/v1/search') open @endif>
                                <summary class="cursor-pointer">
                                    <span class="font-mono text-xs font-semibold" style="color: var(--accent);">GET</span>
                                    @if ($operation['deprecated'])
                                        <span class="text-xs font-semibold" style="color: var(--error);">{{ __('Deprecated') }}</span>
                                    @endif
                                    <span class="text-lg">{{ $operation['summary'] }}</span>
                                    <span class="mt-1 block break-all font-mono text-sm" style="color: var(--muted);">{{ $operation['path'] }}</span>
                                </summary>
                                <div class="mt-4">
                                    @if ($operation['description'] !== '')
                                        <p>{{ $operation['description'] }}</p>
                                    @endif
                                    @if ($operation['parameters'] === [])
                                        <p class="mt-3 text-sm" style="color: var(--muted);">{{ __('This operation takes no parameters.') }}</p>
                                    @else
                                        <h4 class="mt-4 text-lg">{{ __('Parameters') }}</h4>
                                        <dl class="mt-2 space-y-3">
                                            @foreach ($operation['parameters'] as $param)
                                                <div>
                                                    <dt class="break-all font-mono text-sm">{{ $param['name'] }}
                                                        <span class="font-sans text-xs" style="color: var(--muted);">{{ $param['in'] }} · {{ $param['type'] }}@if ($param['required']) · {{ __('required') }}@endif</span>
                                                    </dt>
                                                    @if ($param['description'] !== '')
                                                        <dd class="text-sm" style="color: var(--muted);">{{ $param['description'] }}</dd>
                                                    @endif
                                                    @if ($param['constraints'] !== '')
                                                        <dd class="text-xs" style="color: var(--muted);">{{ $param['constraints'] }}</dd>
                                                    @endif
                                                </div>
                                            @endforeach
                                        </dl>
                                    @endif

                                    @if (is_array($operation['snippets'] ?? null))
                                        <h4 class="mt-5 text-lg">{{ __('Request') }}</h4>
                                        <x-code-block :id="'curl-'.$operation['html_id']" :label="__('curl example for :summary', ['summary' => $operation['summary']])">{{ $operation['snippets']['curl'] }}</x-code-block>
                                        <x-code-block :id="'js-'.$operation['html_id']" :label="__('JavaScript example for :summary', ['summary' => $operation['summary']])">{{ $operation['snippets']['javascript'] }}</x-code-block>
                                        <x-code-block :id="'py-'.$operation['html_id']" :label="__('Python example for :summary', ['summary' => $operation['summary']])">{{ $operation['snippets']['python'] }}</x-code-block>
                                    @endif

                                    @if (is_array($operation['example'] ?? null))
                                        <h4 class="mt-5 text-lg">{{ __('Example response') }}</h4>
                                        @if ($operation['example']['truncated'])
                                            <p class="mt-2 text-sm" style="color: var(--muted);">{{ __('Showing the start of the response.') }}</p>
                                        @endif
                                        <x-code-block :id="'example-'.$operation['html_id']" :label="__('Example response for :summary', ['summary' => $operation['summary']])">{{ $operation['example']['body'] }}</x-code-block>
                                    @endif

                                    <h4 class="mt-5 text-lg">{{ __('Try it') }}</h4>
                                    <form class="mt-3 space-y-3" method="get" action="{{ route('api') }}#{{ $operation['html_id'] }}">
                                        <input type="hidden" name="try" value="{{ $operation['id'] }}">
                                        @foreach ($operation['parameters'] as $param)
                                            @php
                                                $fieldId = $operation['html_id'].'-'.$param['name'];
                                                $current = data_get($call, 'id') === $operation['id']
                                                    ? (string) data_get($call, 'input.'.$param['name'], '')
                                                    : (string) ($operation['example_input'][$param['name']] ?? '');
                                            @endphp
                                            <div>
                                                <label class="text-sm font-semibold" for="{{ $fieldId }}">{{ $param['name'] }}@if ($param['required']) <span style="color: var(--error);">*</span>@endif</label>
                                                @if ($param['type'] === 'boolean')
                                                    <select id="{{ $fieldId }}" name="p[{{ $param['name'] }}]" class="mt-1 block w-full min-h-11 rounded-lg border px-3 text-base" style="background: var(--bg); border-color: var(--border); color: var(--text);" @if ($param['description'] !== '') aria-describedby="{{ $fieldId }}-hint" @endif>
                                                        <option value="" @selected($current === '')>{{ __('Omit') }}</option>
                                                        <option value="true" @selected($current === 'true')>true</option>
                                                        <option value="false" @selected($current === 'false')>false</option>
                                                    </select>
                                                @else
                                                    <input id="{{ $fieldId }}" name="p[{{ $param['name'] }}]" value="{{ $current }}"
                                                        @if (in_array($param['type'], ['integer', 'number'], true))
                                                            type="number" inputmode="decimal" step="{{ $param['type'] === 'integer' ? '1' : 'any' }}"
                                                            @if ($param['minimum'] !== null) min="{{ $param['minimum'] }}" @endif
                                                            @if ($param['name'] === 'limit') max="{{ \App\Services\CatalogueDocs\CatalogueCall::LIMIT_CAP }}" @elseif ($param['maximum'] !== null) max="{{ $param['maximum'] }}" @endif
                                                        @else
                                                            type="text" maxlength="200" autocomplete="off"
                                                        @endif
                                                        class="mt-1 block w-full min-h-11 rounded-lg border px-3 text-base" style="background: var(--bg); border-color: var(--border); color: var(--text);"
                                                        @if ($param['description'] !== '') aria-describedby="{{ $fieldId }}-hint" @endif>
                                                @endif
                                                @if ($param['description'] !== '')
                                                    <p id="{{ $fieldId }}-hint" class="mt-1 text-sm" style="color: var(--muted);">{{ $param['description'] }}</p>
                                                @endif
                                            </div>
                                        @endforeach
                                        @foreach ($operation['parameters'] as $param)
                                            @if ($param['name'] === 'limit' && $param['maximum'] !== null)
                                                <p class="text-sm" style="color: var(--muted);">{{ __('Calls from this form send at most :cap for limit. A direct request can use up to :max.', ['cap' => \App\Services\CatalogueDocs\CatalogueCall::LIMIT_CAP, 'max' => $param['maximum']]) }}</p>
                                            @endif
                                        @endforeach
                                        <button type="submit" class="inline-flex min-h-11 w-full items-center justify-center rounded-lg px-4 text-sm font-semibold sm:w-auto" style="background-color: var(--accent); color: #07090f;">{{ __('Run read-only request') }}</button>
                                    </form>

                                    @if (data_get($call, 'id') === $operation['id'])
                                        <div class="mt-4" role="region" aria-label="{{ __('Response for :summary', ['summary' => $operation['summary']]) }}">
                                            @if (data_get($call, 'ok') === true)
                                                <h4 class="text-lg">{{ __('Response') }} <span class="font-mono text-sm">HTTP {{ data_get($call, 'status') }}</span></h4>
                                                <p class="mt-2 break-all font-mono text-xs" style="color: var(--muted);">{{ data_get($call, 'url') }}</p>
                                                @if (data_get($call, 'truncated'))
                                                    <p class="mt-2 text-sm" style="color: var(--muted);">{{ __('Showing the start of the response.') }}</p>
                                                @endif
                                                <x-code-block :id="'response-'.$operation['html_id']" :label="__('Response for :summary', ['summary' => $operation['summary']])">{{ data_get($call, 'body') }}</x-code-block>
                                            @else
                                                <p class="mt-2" role="alert">{{ data_get($call, 'message') }}</p>
                                            @endif
                                        </div>
                                    @endif
                                </div>
                            </details>
                        @endforeach
                    </div>
                @endforeach
            @endif
        </section>

        <section class="surface p-5 sm:p-6" aria-labelledby="limits">
            <h2 id="limits" tabindex="-1" class="font-serif text-2xl">{{ __('Limits and attribution') }}</h2>
            <h3 class="mt-4 text-xl">{{ __('Rate limits') }}</h3>
            <ul class="mt-2 list-disc space-y-2 pl-6">
                <li>{{ __('Catalogue routes are limited to 60 requests per minute per IP. A route-specific limit replaces the application default of 60 per minute and 1,000 per day.') }}</li>
                <li>{{ __('Night planning, GET /api/v1/observing/night, is limited to 10 requests per minute per IP.') }}</li>
                <li>{{ __('The public MCP endpoint allows 60 requests per minute and request bodies up to 64 kB.') }}</li>
                @if ($plannerNight)
                    <li>{{ __('The MCP tool plan_observing_night is limited to 10 requests per minute.') }}</li>
                @endif
                @if ($plannerDiscover)
                    <li>{{ __('The MCP tool discover_observing_targets is limited to 5 requests per minute.') }}</li>
                @endif
            </ul>
            <h3 class="mt-5 text-xl">{{ __('Attribution') }}</h3>
            @if (is_array($catalogue) && ($catalogue['license_name'] || $catalogue['contact_url']))
                <p class="mt-2">
                    @if ($catalogue['license_name'])
                        {{ __('The API software is :license.', ['license' => $catalogue['license_name']]) }}
                        @if ($catalogue['license_url'])
                            <a class="underline" href="{{ $catalogue['license_url'] }}" rel="noopener">{{ $catalogue['license_url'] }}</a>
                        @endif
                    @endif
                    @if ($catalogue['contact_url'])
                        <a class="underline" href="{{ $catalogue['contact_url'] }}" rel="noopener">{{ $catalogue['contact_name'] ?: $catalogue['contact_url'] }}</a>
                    @endif
                </p>
            @endif
            <p class="mt-2">{{ __('Keep the source cited on each record when you reuse a measurement. Dataset terms are separate from the API software licence.') }}</p>
        </section>

        <section class="surface p-5 sm:p-6" aria-labelledby="mcp">
            <h2 id="mcp" tabindex="-1" class="font-serif text-2xl">{{ __('MCP server') }}</h2>
            <p class="mt-3">{{ __('solar-mcp is the public streamable HTTP server at the URL below. It identifies itself to clients as :name. Connect it with that URL and no API key, no headers and no OAuth client.', ['name' => $mcpName]) }}</p>
            @if (is_array($server) && $server['version'])
                <p class="mt-2 text-sm" style="color: var(--muted);">{{ __('Reported version') }} {{ $server['version'] }}</p>
            @endif
            @if (is_array($server) && $server['instructions'] !== '')
                <p class="mt-3">{{ $server['instructions'] }}</p>
            @elseif (! is_array($server))
                <p class="mt-3" role="status">{{ __('The live tool list could not be loaded, so this page is not showing tool names.') }}</p>
            @endif
            <x-code-block id="mcp-url" :label="__('MCP server URL')">{{ $mcpUrl }}</x-code-block>

            <h3 class="mt-6 text-xl">{{ __('Cursor') }}</h3>
            <ol class="mt-2 list-decimal space-y-2 pl-6">
                <li>{{ __('Open Cursor Settings → MCP, or create .cursor/mcp.json in a project or ~/.cursor/mcp.json for every project.') }}</li>
                <li>{{ __('Add the server with the URL form Cursor documents for remote streamable HTTP. Leave out headers and auth.') }}</li>
                <li>{{ __('Reload Cursor and confirm the server is connected before asking it to call a tool.') }}</li>
            </ol>
            <x-code-block id="cursor-mcp" :label="__('Cursor MCP configuration')">{{ $mcpConfig['cursor'] }}</x-code-block>
            <p class="mt-2 text-sm"><a class="underline" href="https://cursor.com/docs/context/mcp" rel="noopener">{{ __('Cursor MCP documentation') }}</a></p>

            <h3 class="mt-6 text-xl">{{ __('Grok') }}</h3>
            <p class="mt-2">{{ __('Grok reads remote MCP servers from ~/.grok/config.toml. The same URL works with the grok mcp command. Grok can also load a Cursor mcp.json file.') }}</p>
            <x-code-block id="grok-mcp" :label="__('Grok MCP configuration')">{{ $mcpConfig['grok'] }}</x-code-block>
            <x-code-block id="grok-mcp-cli" :label="__('Grok MCP command')">{{ $mcpConfig['grok_cli'] }}</x-code-block>
            <p class="mt-2 text-sm"><a class="underline" href="https://docs.x.ai/build/features/mcp-servers" rel="noopener">{{ __('Grok MCP documentation') }}</a></p>

            @if (is_array($server) && $server['prompts'] !== [])
                <h3 class="mt-6 text-xl">{{ __('Example prompts') }}</h3>
                <ul class="mt-2 list-disc space-y-2 pl-6">
                    @foreach ($server['prompts'] as $prompt)
                        <li>{{ $prompt }}</li>
                    @endforeach
                </ul>
            @endif

            @if (is_array($server))
                <h3 class="mt-6 text-xl">{{ __('Tools') }}</h3>
                <p class="mt-2 text-sm" style="color: var(--muted);">{{ __('Names, descriptions and parameters are the read-only tools the server reported. Each one advertises a read-only annotation.') }}</p>
                <div class="mt-3 space-y-3">
                    @forelse ($server['tools'] as $tool)
                        <details class="rounded-lg border p-3" style="border-color: var(--border);">
                            <summary class="min-h-11 cursor-pointer break-all">
                                <span class="font-mono text-sm font-semibold">{{ $tool['name'] }}</span>
                                @if ($tool['summary'] !== '')
                                    <span class="mt-1 block text-sm" style="color: var(--muted);">{{ $tool['summary'] }}</span>
                                @endif
                            </summary>
                            @if ($tool['description'] !== '' && $tool['description'] !== $tool['summary'])
                                <p class="mt-3 whitespace-pre-wrap text-sm">{{ $tool['description'] }}</p>
                            @endif
                            @if ($tool['parameters'] !== [])
                                <ul class="mt-3 space-y-2">
                                    @foreach ($tool['parameters'] as $param)
                                        <li class="text-sm">
                                            <span class="font-mono">{{ $param['name'] }}</span>
                                            @if ($param['required']) <span style="color: var(--muted);">{{ __('required') }}</span> @endif
                                            @if ($param['description'] !== '') <span style="color: var(--muted);">— {{ $param['description'] }}</span> @endif
                                        </li>
                                    @endforeach
                                </ul>
                            @endif
                        </details>
                    @empty
                        <p>{{ __('The server reported no read-only tools.') }}</p>
                    @endforelse
                </div>

                @if ($server['resources'] !== [])
                    <h3 class="mt-6 text-xl">{{ __('Resources') }}</h3>
                    <ul class="mt-2 space-y-2">
                        @foreach ($server['resources'] as $resource)
                            <li>
                                <p class="break-all font-mono text-sm">{{ $resource['uri'] }}</p>
                                @if ($resource['description'] !== '')
                                    <p class="text-sm" style="color: var(--muted);">{{ $resource['description'] }}</p>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                @endif
            @endif

            <p class="mt-6 text-sm"><a class="underline" href="https://github.com/Wicked-Sick-Ltd/solar-system-db/blob/main/mcp-server/README.md" rel="noopener">{{ __('MCP server README') }}</a> · <a class="underline" href="{{ route('plugin') }}">{{ __('Plugin setup guide') }}</a></p>
        </section>
    </div>
</div>
