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
        ->assertSee(sprintf('"url": "%s"', $mcpUrl), escape: false);
});
