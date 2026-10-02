<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Releases\ReleasePublisher;
use Illuminate\Console\Command;
use Throwable;

final class PublishCommunityRelease extends Command
{
    protected $signature = 'universe:releases:publish {--commit= : Exact deployed build commit}';

    protected $description = 'Publish reviewed notes to the local ledger after exact live build readiness verification';

    public function handle(ReleasePublisher $publisher): int
    {
        try {
            $release = $publisher->publish((string) $this->option('commit'));
        } catch (Throwable) {
            $this->error('Release publication failed. Check reviewed notes, local build identity, HTTPS readiness and database migrations. No community message was sent.');

            return self::FAILURE;
        }
        $this->info($release ? 'Published community release '.$release->version.'. No community message was sent.' : 'Community preview (0.0.0): readiness verified; publication skipped.');

        return self::SUCCESS;
    }
}
