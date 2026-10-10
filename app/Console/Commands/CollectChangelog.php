<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Changelog\ChangelogNotes;
use App\Services\Changelog\GitHubMergedPullRequests;
use Illuminate\Console\Command;
use JsonException;
use RuntimeException;
use Throwable;

final class CollectChangelog extends Command
{
    protected $signature = 'universe:changelog:collect
        {--repository= : GitHub owner/name}
        {--output= : Path of the JSON notes file to write}
        {--input= : Local JSON pull-request file; skips the network}
        {--check : Fail when the committed notes differ from the collected ones}';

    protected $description = 'Collect merged pull requests into visitor-facing release notes';

    public function handle(ChangelogNotes $notes, GitHubMergedPullRequests $github): int
    {
        $repository = (string) ($this->option('repository') ?: config('changelog.repository'));
        $output = (string) ($this->option('output') ?: config('changelog.path'));

        try {
            $source = $this->source($github, $repository);
            $document = $notes->document($repository, $source['pulls'], $source['releases']);
            if ($this->option('check')) {
                if (! $notes->sameEntries($output, $document)) {
                    $this->error('Committed release notes do not match merged pull requests. Run php artisan universe:changelog:collect and commit the JSON file.');

                    return self::FAILURE;
                }
                $this->info('Committed release notes match merged pull requests.');

                return self::SUCCESS;
            }
            $notes->write($output, $document);
        } catch (Throwable $exception) {
            $this->error($this->safeMessage($exception));

            return self::FAILURE;
        }

        $count = array_sum(array_map(fn (array $group): int => count($group['entries']), $document['groups']));
        $this->info('Wrote '.$count.' release notes.');

        return self::SUCCESS;
    }

    /**
     * @return array{pulls:list<array<string,mixed>>,releases:list<array<string,mixed>>}
     */
    private function source(GitHubMergedPullRequests $github, string $repository): array
    {
        $input = $this->option('input');
        if (! is_string($input) || $input === '') {
            return $github->fetch($repository);
        }
        if (! is_file($input) || filesize($input) > 2_000_000) {
            throw new RuntimeException('The pull request file is missing or too large.');
        }

        try {
            $data = json_decode(file_get_contents($input) ?: '', true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new RuntimeException('The pull request file is not valid JSON.');
        }
        if (is_array($data) && array_is_list($data)) {
            return ['pulls' => $data, 'releases' => []];
        }
        if (! is_array($data) || ! is_array($data['pulls'] ?? null)) {
            throw new RuntimeException('The pull request file must contain a list of pull requests.');
        }

        return [
            'pulls' => $data['pulls'],
            'releases' => is_array($data['releases'] ?? null) ? $data['releases'] : [],
        ];
    }

    private function safeMessage(Throwable $exception): string
    {
        $message = $exception->getMessage();
        if ($message === '' || preg_match('/ghp_[A-Za-z0-9]+|github_pat_[A-Za-z0-9_]+|Bearer\s+\S+|sk_live_[A-Za-z0-9]+/', $message) === 1) {
            return 'Could not collect release notes.';
        }

        return $message;
    }
}
