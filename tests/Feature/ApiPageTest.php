<?php

declare(strict_types=1);

use App\Support\Links;

beforeEach(fn () => fakeSolar());

it('shows the public REST and MCP endpoints', function () {
    $mcpUrl = Links::mcp();

    $this->get('/api')
        ->assertOk()
        ->assertSee(config('services.solar.base_url'))
        ->assertSee($mcpUrl)
        ->assertSee(route('plugin'), escape: false)
        ->assertSee('Plugin setup guide')
        ->assertSee('ChatGPT, Codex, Claude, Cursor and GitHub Copilot');
});
