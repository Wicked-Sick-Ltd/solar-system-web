<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Releases\ReleaseCatalog;
use Illuminate\Console\Command;
use Illuminate\Database\QueryException;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

final class RenderCommunityRelease extends Command
{
    protected $signature = 'universe:releases:render {version?} {--format=markdown : markdown or json}';

    protected $description = 'Render reviewed community copy locally for review; never send or publish it';

    public function handle(ReleaseCatalog $catalog): int
    {
        $version = (string) ($this->argument('version') ?? config('releases.version'));
        $format = $this->option('format');
        if (! in_array($format, ['markdown', 'json'], true)) {
            $this->error('Choose markdown or json.');

            return self::FAILURE;
        }
        try {
            try {
                $published = $catalog->published()->firstWhere('version', $version);
            } catch (QueryException) {
                // Local editorial review also works before database setup.
                $published = null;
            }
            $notes = $published->notes ?? $catalog->notes($version);
            $status = $published ? 'Published' : 'DRAFT — unreleased, for review only';
            if ($format === 'json') {
                $this->output->write(json_encode(['version' => $version, 'status' => $status, 'notes' => $notes], JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)."\n", false, OutputInterface::OUTPUT_RAW);
            } else {
                $lines = ['# Public Universe '.$version.' — '.$notes['title'], '', $status, '', $notes['summary']];
                foreach ($notes['sections'] as $heading => $items) {
                    array_push($lines, '', '## '.$heading, '');
                    foreach ($items as $item) {
                        $lines[] = '- '.$item;
                    }
                }
                $this->output->write(implode("\n", $lines)."\n", false, OutputInterface::OUTPUT_RAW);
            }
        } catch (Throwable) {
            $this->error('Could not render this version. Check reviewed release metadata and database readiness.');

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
