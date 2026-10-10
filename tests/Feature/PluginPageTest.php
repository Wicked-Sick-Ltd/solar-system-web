<?php

declare(strict_types=1);

use App\Support\KeyStage;

beforeEach(fn () => fakeSolar());

it('explains setup for every supported client and distinguishes tools from skills', function () {
    $this->get('/plugin')->assertOk()
        ->assertSee('Astronomy in your AI assistant')
        ->assertSee('ChatGPT')->assertSee('Codex')->assertSee('Cursor')
        ->assertSee('GitHub Copilot')->assertSee('Claude Code')
        ->assertSee('It does not install the seven skills.')
        ->assertSee('The plugin has not been published in the public directory.')
        ->assertSee('codex mcp add solar-system-db --url')
        ->assertSee('copilot plugin install /absolute/path/to/solar-plugin')
        ->assertSee('claude --plugin-dir /absolute/path/to/solar-plugin');
});

it('provides working subsection anchors and all seven workflows', function () {
    $response = $this->get('/plugin')->assertOk();

    foreach (['getting-started', 'chatgpt', 'codex', 'cursor', 'copilot', 'claude', 'skills', 'check-connection'] as $section) {
        $response->assertSee('href="#'.$section.'"', false)
            ->assertSee('id="'.$section.'" tabindex="-1"', false);
    }

    foreach (['tonight-sky', 'lesson-builder', 'space-fact-check', 'object-explainer', 'close-approach-watch', 'sky-this-week', 'solar-data-audit'] as $skill) {
        $response->assertSee($skill);
    }

    $response
        ->assertSee(KeyStage::compact('KS3'))
        ->assertSee('with the matching US grades');
});

it('keeps connection instructions tied to the configured backend through a domain move', function () {
    config(['services.solar.base_url' => 'https://astronomy.example/api/v1']);

    $this->get('/plugin')->assertOk()
        ->assertSee('https://astronomy.example/mcp')
        ->assertSee('codex mcp add solar-system-db --url https://astronomy.example/mcp')
        ->assertDontSee('https://api.sol.wickedsick.com/mcp');
});

it('clearly states pending live access until operators mark the connection ready', function () {
    config(['plugin.connection_ready' => false]);
    $this->get('/plugin')->assertOk()->assertSee('Public connection being prepared')
        ->assertSee('Cloudflare issue');

    config(['plugin.connection_ready' => true]);
    $this->get('/plugin')->assertOk()->assertDontSee('Public connection being prepared');
});

it('emits canonical metadata and uses public editorial caching', function () {
    $this->get('/plugin')->assertOk()
        ->assertSee('<title>Astronomy plugin · '.config('site.name').'</title>', false)
        ->assertSee('<link rel="canonical" href="'.url('/plugin').'">', false)
        ->assertHeader('Cache-Control', 'max-age=0, public, s-maxage=600, stale-while-revalidate=86400');
});
