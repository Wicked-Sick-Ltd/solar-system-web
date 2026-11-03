<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Releases\ReleasePublisher;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Throwable;

#[Signature('universe:releases:publish {--commit= : Exact deployed build commit}')]
#[Description('Publish reviewed notes to the local ledger after exact live build readiness verification')]
final class PublishCommunityRelease extends Command
{
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
