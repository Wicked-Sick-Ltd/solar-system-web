<?php

declare(strict_types=1);

namespace App\Services\ApiAccess;

use App\Models\ApiAccessRequest;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Best-effort notices after a request is stored. Unset Slack or Asana
 * settings are no-ops. A failed webhook does not undo the stored row.
 */
final class ApiAccessNotifier
{
    public function notify(ApiAccessRequest $request): void
    {
        $this->slack($request);
        $this->asana($request);
    }

    private function slack(ApiAccessRequest $request): void
    {
        $url = config('api_access.slack_webhook_url');
        if (! is_string($url) || ! str_starts_with($url, 'https://')) {
            return;
        }

        try {
            Http::timeout(5)->post($url, ['text' => $this->summary($request)]);
        } catch (Throwable) {
            // The webhook URL can embed a secret. Do not log the transport error.
        }
    }

    private function asana(ApiAccessRequest $request): void
    {
        $token = config('api_access.asana_access_token');
        $project = config('api_access.asana_project_id');
        if (! is_string($token) || $token === '' || ! is_string($project) || $project === '') {
            return;
        }

        try {
            Http::timeout(5)->withToken($token)->post('https://app.asana.com/api/1.0/tasks', [
                'data' => [
                    'name' => 'API access: '.$request->organisation,
                    'notes' => $this->summary($request),
                    'projects' => [$project],
                ],
            ]);
        } catch (Throwable) {
            // Same as Slack: keep the stored request, drop the transport error.
        }
    }

    private function summary(ApiAccessRequest $request): string
    {
        $volumes = config('api_access.volumes');
        $volume = is_array($volumes) ? (string) ($volumes[$request->expected_volume] ?? $request->expected_volume) : $request->expected_volume;
        $tier = $request->institution_eligible
            ? 'Academic domain: flag for the free institution tier (review before issuing a key).'
            : 'Not flagged as an academic domain.';

        return implode("\n", [
            'Public Universe API access request #'.$request->id,
            'Organisation: '.$request->organisation,
            'Contact: '.$request->contact_name,
            'Email: '.$request->email,
            'Expected volume: '.$volume,
            $tier,
            'Use case:',
            $request->use_case,
        ]);
    }
}
