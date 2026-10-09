<div class="mx-auto max-w-4xl">
    <x-page-header :title="__('Astronomy in your AI assistant')" :eyebrow="__('The Public Universe plugin')"
        :lead="__('Explore astronomical data, prepare lessons and plan an evening under the stars in ChatGPT, Codex, Cursor, GitHub Copilot or Claude Code.')" />

    @unless ($connectionReady)
        <aside class="surface mb-6 border-l-4 p-5" style="border-left-color: var(--accent);" aria-labelledby="connection-status-heading">
            <h2 id="connection-status-heading" class="font-semibold">{{ __('Public connection being prepared') }}</h2>
            <p class="mt-2 text-sm leading-relaxed">{{ __('The plugin is available to download. Public data connections are currently affected by a Cloudflare issue, which we will address during the move to publicuniverse.net. You can prepare your installation now; a successful live connection is still pending.') }}</p>
            <a class="mt-2 inline-block text-sm underline" href="{{ $repository }}/blob/main/docs/validation.md">{{ __('Connection status and checks') }}</a>
        </aside>
    @endunless

    <x-section-navigation :sections="[
        ['id' => 'getting-started', 'label' => __('Start here')],
        ['id' => 'chatgpt', 'label' => 'ChatGPT'],
        ['id' => 'codex', 'label' => 'Codex'],
        ['id' => 'cursor', 'label' => 'Cursor'],
        ['id' => 'copilot', 'label' => 'GitHub Copilot'],
        ['id' => 'claude', 'label' => 'Claude Code'],
        ['id' => 'skills', 'label' => __('Skills and examples')],
        ['id' => 'check-connection', 'label' => __('Check your connection')],
    ]" />

    <div class="space-y-8 leading-relaxed">
        <section class="surface p-5 sm:p-6" aria-labelledby="getting-started">
            <h2 id="getting-started" tabindex="-1" class="font-serif text-2xl">{{ __('Start with your assistant') }}</h2>
            <p class="mt-3">{{ __('The free, open-source package is called solar. It combines a data connection with seven reusable skills: instructions that help your assistant ask the right questions and explain the results.') }}</p>
            <p class="mt-3">{{ __('A tools-only connection uses MCP (Model Context Protocol) to retrieve data. It does not install the seven skills. Choose one setup route per assistant to avoid duplicates. Your assistant may require its own subscription or administrator permission.') }}</p>
            <p class="mt-3"><a class="underline" href="{{ $repository }}">{{ __('Get the plugin from GitHub') }}</a>{{ __(' — download the repository ZIP and extract it, or clone it into a new folder:') }}</p>
            <pre tabindex="0" aria-label="{{ __('Download the plugin with Git') }}" class="mt-3 overflow-x-auto rounded-lg p-4 text-sm" style="background: var(--bg);"><code>git clone {{ $repository }}.git</code></pre>
            <p class="mt-3 text-sm" style="color: var(--muted);">{{ __('In the examples below, replace /absolute/path/to/solar-plugin with the folder you extracted or cloned. Keep existing client settings when adding configuration.') }}</p>
            <h3 class="mt-5 font-semibold">{{ __('Data connection details') }}</h3>
            <dl class="mt-2 grid gap-2 text-sm sm:grid-cols-[auto_1fr] sm:gap-x-5">
                <dt class="font-semibold">{{ __('Server name') }}</dt><dd><code>solar-system-db</code></dd>
                <dt class="font-semibold">{{ __('Transport') }}</dt><dd>Streamable HTTP</dd>
                <dt class="font-semibold">{{ __('Authentication') }}</dt><dd>{{ __('The public service is intended to require no API key.') }}</dd>
            </dl>
            <pre tabindex="0" aria-label="{{ __('MCP server URL') }}" class="mt-3 overflow-x-auto rounded-lg p-4 text-sm" style="background: var(--bg);"><code>{{ $mcpUrl }}</code></pre>
        </section>

        <section class="surface p-5 sm:p-6" aria-labelledby="chatgpt">
            <h2 id="chatgpt" tabindex="-1" class="font-serif text-2xl">ChatGPT</h2>
            <p class="mt-2 text-sm" style="color: var(--muted);">{{ __('Tools-only connection · availability depends on your account and workspace policy') }}</p>
            <ol class="mt-4 list-decimal space-y-2 pl-6">
                <li>{{ __('In Settings → Security and login, enable Developer mode if it is available.') }}</li>
                <li><a class="underline" href="https://chatgpt.com/plugins">{{ __('Open ChatGPT Plugins') }}</a>{{ __(' and select the plus button to create a connection named Public Universe (Solar).') }}</li>
                <li>{{ __('Enter the MCP server URL above, choose no authentication, and review the tools it discovers.') }}</li>
                <li>{{ __('Start a new conversation and select the connection from the tools menu. Try the connection check below.') }}</li>
            </ol>
            <p class="mt-3">{{ __('This connects the data tools. Packaged skills need a supported plugin installation; connecting the URL alone does not add them. The plugin has not been published in the public directory.') }}</p>
            <p class="mt-3 text-sm"><a class="underline" href="{{ $repository }}/blob/main/docs/openai.md#chatgpt">{{ __('Full ChatGPT setup and skill options') }}</a> · <a class="underline" href="https://developers.openai.com/plugins/deploy/connect-chatgpt">{{ __('Official OpenAI connection guide') }}</a></p>
        </section>

        <section class="surface p-5 sm:p-6" aria-labelledby="codex">
            <h2 id="codex" tabindex="-1" class="font-serif text-2xl">Codex</h2>
            <p class="mt-3">{{ __('For the CLI, register the data connection:') }}</p>
            <pre tabindex="0" aria-label="{{ __('Codex setup commands') }}" class="mt-3 overflow-x-auto rounded-lg p-4 text-sm" style="background: var(--bg);"><code>codex mcp add solar-system-db --url {{ $mcpUrl }}
codex mcp list</code></pre>
            <p class="mt-3">{{ __('To add workflows, copy each desired whole folder from skills/ into .agents/skills/ in your project, or ~/.agents/skills/ for personal use. Keep SKILL.md and references/ together. Start a new session and invoke $tonight-sky with a town, date and timezone.') }}</p>
            <p class="mt-3">{{ __('Codex also supports the full portable plugin through a local or Git-backed marketplace. Use that route instead of manually adding the same tools and skills twice.') }}</p>
            <p class="mt-3 text-sm"><a class="underline" href="{{ $repository }}/blob/main/docs/openai.md#codex">{{ __('Full Codex and marketplace setup') }}</a> · <a class="underline" href="https://learn.chatgpt.com/docs/extend/mcp?surface=cli">{{ __('Official MCP guide') }}</a> · <a class="underline" href="https://learn.chatgpt.com/docs/build-skills">{{ __('Official skills guide') }}</a></p>
        </section>

        <section class="surface p-5 sm:p-6" aria-labelledby="cursor">
            <h2 id="cursor" tabindex="-1" class="font-serif text-2xl">Cursor</h2>
            <ol class="mt-4 list-decimal space-y-2 pl-6">
                <li>{{ __('Create a new folder at ~/.cursor/plugins/local/solar. Copy the plugin contents into it, including hidden files.') }}</li>
                <li>{{ __('Check that plugin.json, mcp.json and skills/ sit directly inside that folder, without an extra solar-plugin/ folder.') }}</li>
                <li>{{ __('Restart Cursor or run Developer: Reload Window. Open Customize and check for the Solar skills and solar-system-db server.') }}</li>
            </ol>
            <p class="mt-3 text-sm" style="color: var(--muted);">{{ __('Use a real folder: Cursor ignores links to folders outside its local plugin directory. Local imports must be allowed by your organisation.') }}</p>
            <p class="mt-3 text-sm"><a class="underline" href="{{ $repository }}/blob/main/docs/clients.md#cursor-full-plugin">{{ __('Full Cursor setup and tools-only option') }}</a> · <a class="underline" href="https://prod.cursor.com/docs/plugins#test-plugins-locally">{{ __('Official Cursor guide') }}</a></p>
        </section>

        <section class="surface p-5 sm:p-6" aria-labelledby="copilot">
            <h2 id="copilot" tabindex="-1" class="font-serif text-2xl">GitHub Copilot</h2>
            <h3 class="mt-4 font-semibold">{{ __('Copilot CLI') }}</h3>
            <pre tabindex="0" aria-label="{{ __('Copilot CLI setup commands') }}" class="mt-3 overflow-x-auto rounded-lg p-4 text-sm" style="background: var(--bg);"><code>copilot plugin install /absolute/path/to/solar-plugin
copilot plugin list</code></pre>
            <p class="mt-3">{{ __('Start a new session and check the plugin, skills and MCP views.') }}</p>
            <h3 class="mt-5 font-semibold">{{ __('Copilot in VS Code') }}</h3>
            <p class="mt-2">{{ __('Merge these entries into your existing user settings.json, using your plugin folder’s absolute path, then reload VS Code:') }}</p>
            <pre tabindex="0" aria-label="{{ __('VS Code plugin settings') }}" class="mt-3 overflow-x-auto rounded-lg p-4 text-sm" style="background: var(--bg);"><code>{
  "chat.plugins.enabled": true,
  "chat.pluginLocations": {
    "/absolute/path/to/solar-plugin": true
  }
}</code></pre>
            <p class="mt-3">{{ __('Check the agent skills and MCP server lists. These local installations do not configure GitHub-hosted cloud agents or other IDEs.') }}</p>
            <p class="mt-3 text-sm"><a class="underline" href="{{ $repository }}/blob/main/docs/clients.md#github-copilot-cli-full-plugin">{{ __('Full Copilot setup and tools-only options') }}</a> · <a class="underline" href="https://docs.github.com/en/copilot/reference/copilot-cli-reference/cli-plugin-reference">{{ __('Official CLI guide') }}</a> · <a class="underline" href="https://code.visualstudio.com/docs/agent-customization/agent-plugins#use-local-plugins">{{ __('Official VS Code guide') }}</a></p>
        </section>

        <section class="surface p-5 sm:p-6" aria-labelledby="claude">
            <h2 id="claude" tabindex="-1" class="font-serif text-2xl">Claude Code</h2>
            <p class="mt-3">{{ __('Load the plugin for a Claude Code session:') }}</p>
            <pre tabindex="0" aria-label="{{ __('Claude Code setup command') }}" class="mt-3 overflow-x-auto rounded-lg p-4 text-sm" style="background: var(--bg);"><code>claude --plugin-dir /absolute/path/to/solar-plugin</code></pre>
            <p class="mt-3">{{ __('Use /mcp to inspect the data connection, then try /solar:tonight-sky. The package includes Claude’s compatibility files, so no marketplace installation is needed for this route.') }}</p>
            <p class="mt-3 text-sm"><a class="underline" href="{{ $repository }}/blob/main/docs/clients.md#claude-code-existing-plugin-compatibility">{{ __('Full Claude Code setup and tools-only option') }}</a> · <a class="underline" href="https://code.claude.com/docs/en/plugins">{{ __('Official Claude Code guide') }}</a></p>
        </section>

        <section aria-labelledby="skills">
            <h2 id="skills" tabindex="-1" class="font-serif text-2xl">{{ __('Seven skills to get you started') }}</h2>
            <dl class="mt-4 grid gap-4 sm:grid-cols-2">
                @foreach ($skills as $name => $description)
                    <div class="surface p-4">
                        <dt class="break-words font-mono text-sm font-semibold">{{ $name }}</dt>
                        <dd class="mt-2 text-sm" style="color: var(--muted);">{{ $description }}</dd>
                    </div>
                @endforeach
            </dl>
            <h3 class="mt-5 font-semibold">{{ __('Try asking') }}</h3>
            <ul class="mt-3 list-disc space-y-2 pl-6">
                <li>{{ __('“Explain Saturn’s rings to a curious eight-year-old, and cite the source of each number.”') }}</li>
                <li>{{ __('“Plan a stargazing evening from Bristol on 15 December 2026, Europe/London timezone. Say which results are calculated and what the weather could change.”') }}</li>
                <li>{{ __('“Create a Key Stage 3 lesson comparing rocky planets and gas giants. Include a worksheet and source links.”') }}</li>
            </ul>
        </section>

        <section class="surface p-5 sm:p-6" aria-labelledby="check-connection">
            <h2 id="check-connection" tabindex="-1" class="font-serif text-2xl">{{ __('Check your connection') }}</h2>
            <p class="mt-3">{{ __('Ask: “Use Solar to retrieve Mars’s catalogue record, cite its source, and distinguish a catalogue value from a computed estimate.” Check that your assistant actually calls a data tool; a plausible answer alone does not establish a connection.') }}</p>
            <p class="mt-3">{{ __('If tools appear without skills, check the copied folders and the client’s skills menu. If neither appears, check the client version, plugin settings and organisation policy. If the server cannot connect, consult the connection status before changing authentication settings.') }}</p>
            <p class="mt-3">{{ __('Keep source links, retrieval dates and units with any answers you reuse. AI explanations can make mistakes; check important results against the original source. The plugin helps plan observations and does not control a telescope.') }}</p>
            <p class="mt-3 text-sm"><a class="underline" href="{{ $repository }}/blob/main/docs/validation.md">{{ __('Connection status and troubleshooting') }}</a> · <a class="underline" href="{{ route('api') }}">{{ __('Use the data API directly') }}</a></p>
        </section>
    </div>
</div>
