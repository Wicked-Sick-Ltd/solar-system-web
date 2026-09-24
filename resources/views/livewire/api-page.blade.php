<div class="mx-auto" style="max-width: var(--container-prose);">
    <x-page-header :title="__('Use the API')" :eyebrow="__('For developers')"
                   :lead="__('This site is a front end over a free, read-only REST API. Query the same data directly — no key, no sign-up.')" />

    <div class="space-y-8 text-base leading-relaxed" style="color: var(--text);">
        <section>
            <h2 class="mb-2 font-serif text-2xl font-medium">{{ __('Base URL') }}</h2>
            <pre class="surface overflow-x-auto p-4 text-sm" style="color: var(--text);"><code>{{ $baseUrl }}</code></pre>
        </section>

        <section>
            <h2 class="mb-2 font-serif text-2xl font-medium">{{ __('MCP endpoint') }}</h2>
            <p class="mb-3" style="color: var(--muted);">{{ __('For AI agents that support Model Context Protocol, use the streamable HTTP endpoint:') }}</p>
            <pre class="surface overflow-x-auto p-4 text-sm" style="color: var(--text);"><code>{{ $mcpUrl }}</code></pre>
        </section>

        <section>
            <h2 class="mb-2 font-serif text-2xl font-medium">{{ __('A worked example') }}</h2>
            <p class="mb-3" style="color: var(--muted);">{{ __('Fetch the full record for Saturn:') }}</p>
            <pre class="surface overflow-x-auto p-4 text-sm" style="color: var(--text);"><code>curl {{ $baseUrl }}/objects/planet-saturn</code></pre>
            <p class="mt-3" style="color: var(--muted);">{{ __('Or search across names, designations and discoverers:') }}</p>
            <pre class="surface overflow-x-auto p-4 text-sm" style="color: var(--text);"><code>curl "{{ $baseUrl }}/search?q=halley&limit=5"</code></pre>
        </section>

        <section>
            <h2 class="mb-2 font-serif text-2xl font-medium">{{ __('Rate limits') }}</h2>
            <p style="color: var(--muted);">
                {{ __('The API is rate-limited to roughly 60 requests per minute and 1,000 per day per IP — generous for browsing and small apps. If you need bulk access, the whole SQLite database is committed to the backend repository and free to clone.') }}
            </p>
        </section>

        <section>
            <h2 class="mb-2 font-serif text-2xl font-medium">{{ __('Full reference') }}</h2>
            <p style="color: var(--muted);">
                {{ __('The complete, always-current endpoint reference is the OpenAPI spec:') }}
            </p>
            <div class="mt-3 flex flex-wrap gap-3">
                <a href="{{ $docsUrl }}" rel="noopener" target="_blank"
                   class="inline-flex items-center gap-2 rounded-lg px-4 py-2.5 text-sm font-semibold"
                   style="background-color: var(--accent); color: #07090f;">{{ __('Interactive docs ↗') }}</a>
                <a href="{{ $openApiUrl }}" rel="noopener" target="_blank"
                   class="inline-flex items-center gap-2 rounded-lg border px-4 py-2.5 text-sm font-medium"
                   style="border-color: var(--border); color: var(--text);">{{ __('OpenAPI JSON ↗') }}</a>
            </div>
        </section>

        <section>
            <h2 class="mb-2 font-serif text-2xl font-medium">{{ __('Use in Claude') }}</h2>
            <p class="mb-3" style="color: var(--muted);">{{ __('Add the MCP server to Claude Desktop (or another MCP client) by URL:') }}</p>
            <pre class="surface overflow-x-auto p-4 text-sm" style="color: var(--text);"><code>{
  "mcpServers": {
    "solar-system-db": {
      "url": "{{ $mcpUrl }}"
    }
  }
}</code></pre>
            <p class="mt-3" style="color: var(--muted);">{{ __('Restart Claude, then open the tools menu to confirm the server is connected.') }}</p>
        </section>
    </div>
</div>
