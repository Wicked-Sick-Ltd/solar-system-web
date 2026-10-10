<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\Changelog\ChangelogCatalog;
use App\Support\Links;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;

final class ReleaseFeedController extends Controller
{
    public function atom(ChangelogCatalog $changelog): Response
    {
        return $this->feed($changelog, 'releases.feed-atom', 'application/atom+xml; charset=UTF-8', 'releases.feed');
    }

    public function rss(ChangelogCatalog $changelog): Response
    {
        return $this->feed($changelog, 'releases.feed-rss', 'application/rss+xml; charset=UTF-8', 'releases.feed.rss');
    }

    private function feed(ChangelogCatalog $changelog, string $view, string $type, string $route): Response
    {
        $entries = $changelog->entries();
        $updated = null;
        foreach ($entries as $entry) {
            $updated = $entry['merged_at'];
            break;
        }
        if (! is_string($updated)) {
            $updated = $changelog->generatedAt() ?? now()->utc()->format('Y-m-d\TH:i:s\Z');
        }
        $updatedAt = Carbon::parse($updated)->utc();

        return response()
            ->view($view, [
                'entries' => $entries,
                'pageUrl' => Links::canonical(route('releases.index')),
                'feedUrl' => Links::canonical(route($route)),
                'updatedAtom' => $updatedAt->toAtomString(),
                'updatedRss' => $updatedAt->toRssString(),
            ])
            ->header('Content-Type', $type)
            ->header('Cache-Control', 'public, max-age=3600');
    }
}
