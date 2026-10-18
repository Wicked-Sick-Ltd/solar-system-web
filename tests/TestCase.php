<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Tests assert on server-rendered HTML, not the asset pipeline. Stubbing
        // Vite means the suite needs no `npm run build` (and no font download),
        // so CI can run composer + Pest alone.
        $this->withoutVite();

        // The suite must not depend on the repository's current version.txt:
        // above the 0.0.0 sentinel, release pages read the community_releases
        // ledger, which only tests using RefreshDatabase migrate. Tests that
        // exercise the ledger set releases.version themselves.
        config(['releases.version' => '0.0.0']);
    }
}
