<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\PrivateObservingWorkspace;
use App\Services\Observing\InvalidSyncPayload;
use App\Services\Observing\ObservingAccountScope;
use App\Services\Observing\SyncPayload;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Throwable;

final class ObservingSyncController extends Controller
{
    public function show(Request $request, SyncPayload $validator): JsonResponse
    {
        if (! $this->sameAccount($request)) {
            return $this->reply(['error' => 'account_changed'], 409);
        }
        try {
            $stored = PrivateObservingWorkspace::where('user_id', $request->user()->id)->first();
            $payload = $stored?->payload;
            if ($payload !== null) {
                // Recheck stored schema after decryption; never send corrupt data to a browser.
                $payload = $validator->validate(json_decode(json_encode($payload, JSON_THROW_ON_ERROR), false, 32, JSON_THROW_ON_ERROR));
            }

            return $this->reply(['revision' => $stored->revision ?? 0, 'state' => $payload === null ? ($stored === null ? 'empty' : 'deleted') : 'saved', 'payload' => $payload,
                'accountScope' => ObservingAccountScope::forUser($request->user())]);
        } catch (Throwable) {
            // Includes lost/rotated encryption keys. Do not log private records or SQL.
            return $this->reply(['error' => 'storage_unavailable'], 503);
        }
    }

    public function update(Request $request, SyncPayload $validator): JsonResponse
    {
        return $this->change($request, $validator, true);
    }

    public function destroy(Request $request, SyncPayload $validator): JsonResponse
    {
        return $this->change($request, $validator, false);
    }

    private function change(Request $request, SyncPayload $validator, bool $upload): JsonResponse
    {
        if (! $this->sameAccount($request)) {
            return $this->reply(['error' => 'account_changed'], 409);
        }
        $body = $request->attributes->get('observingSyncBody');
        $request->attributes->remove('observingSyncBody');
        try {
            $input = $validator->envelope($body, $upload);
        } catch (InvalidSyncPayload) {
            return $this->reply(['error' => 'invalid_document'], 422);
        }
        try {
            $model = new PrivateObservingWorkspace;
            $model->payload = $upload ? $input['payload'] : null;
            $encrypted = $model->getAttributes()['payload'];
            $revision = $input['expectedRevision'];
            $userId = $request->user()->id;
            $changed = DB::transaction(function () use ($userId, $revision, $encrypted): bool {
                $now = now();
                if ($revision === 0) {
                    DB::table('private_observing_workspaces')->insertOrIgnore(['user_id' => $userId, 'revision' => 0, 'payload' => null, 'created_at' => $now, 'updated_at' => $now]);
                }

                // A single compare-and-swap, not a read followed by an unconditional save.
                return DB::table('private_observing_workspaces')->where('user_id', $userId)->where('revision', $revision)
                    ->update(['revision' => $revision + 1, 'payload' => $encrypted, 'updated_at' => $now]) === 1;
            }, 3);

            return $changed ? $this->reply(['revision' => $revision + 1, 'state' => $upload ? 'saved' : 'deleted'])
                : $this->reply(['error' => 'revision_conflict'], 409);
        } catch (Throwable) {
            return $this->reply(['error' => 'storage_unavailable'], 503);
        }
    }

    /** @param array<string,mixed> $body */
    private function reply(array $body, int $status = 200): JsonResponse
    {
        return response()->json($body, $status, ['X-Observing-Sync-Response' => '1', 'Cache-Control' => 'private, no-store', 'Referrer-Policy' => 'no-referrer']);
    }

    private function sameAccount(Request $request): bool
    {
        return hash_equals(ObservingAccountScope::forUser($request->user()), (string) $request->header('X-Observing-Account'));
    }
}
