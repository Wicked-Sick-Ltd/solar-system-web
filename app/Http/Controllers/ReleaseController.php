<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\Changelog\ChangelogCatalog;
use App\Services\Releases\ReleaseCatalog;
use App\Support\Seo;
use Illuminate\Http\Response;

final class ReleaseController
{
    public function __invoke(ReleaseCatalog $catalog, ChangelogCatalog $changelog, ?string $version = null): Response
    {
        $releases = $catalog->published();
        $changes = [];
        if ($version !== null) {
            abort_unless(ReleaseCatalog::validVersion($version), 404);
            $releases = $releases->where('version', $version);
            abort_if($releases->isEmpty(), 404);
        } else {
            $changes = $changelog->groups();
        }
        app(Seo::class)->title($version ? __('What’s new in :version', ['version' => $version]) : __('What’s new'))
            ->description(__('Shipped improvements to Public Universe, with links to the changes.'));

        return response()->view('releases.index', compact('releases', 'version', 'changes'))->header('Cache-Control', 'no-store, private');
    }
}
