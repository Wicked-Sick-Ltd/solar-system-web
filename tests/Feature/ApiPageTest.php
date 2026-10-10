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
        ->assertSee('"mcpServers"', escape: false)
        ->assertSee('"solar-system-db"', escape: false)
        ->assertSee(sprintf('"url": "%s"', $mcpUrl), escape: false)
        ->assertSee(route('plugin'), escape: false)
        ->assertSee('tabindex="0"', false)
        ->assertSee('role="region"', false)
        ->assertSee('aria-label="API base URL"', false)
        ->assertSee('aria-label="Example search request"', false)
        ->assertSee('Plugin setup guide')
        ->assertSee('ChatGPT, Codex, Claude, Cursor and GitHub Copilot');
});
