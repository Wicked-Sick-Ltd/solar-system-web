<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\Releases\ReleaseCatalog;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Throwable;

final class ReleaseHealthController
{
    public function __invoke(): JsonResponse
    {
        $version = (string) config('releases.version');
        $commit = (string) config('releases.commit');
        $databaseReady = false;
        try {
            DB::table('community_releases')->limit(1)->get(['version', 'commit', 'notes', 'published_at']);
            $databaseReady = true;
        } catch (Throwable) {
            // Never expose connection strings, SQL, credentials or exception text.
        }
        $ready = $databaseReady && ReleaseCatalog::validVersion($version) && ReleaseCatalog::validCommit($commit);

        return response()->json(['version' => ReleaseCatalog::validVersion($version) ? $version : null, 'commit' => ReleaseCatalog::validCommit($commit) ? $commit : null, 'database_ready' => $databaseReady], $ready ? 200 : 503,
            ['Cache-Control' => 'no-store, private', 'Pragma' => 'no-cache', 'Expires' => '0']);
    }
}
