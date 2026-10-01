<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\VisibilityAlert;
use App\Notifications\VisibilityUpAfterDarkNotification;
use App\Services\SolarApi\Exceptions\SolarApiException;
use App\Services\SolarApi\SolarApiClient;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Throwable;

final class SendVisibilityAlerts extends Command
{
    protected $signature = 'alerts:send-visibility';

    protected $description = 'Email users when a saved object is up after dark from their saved location';

    public function handle(SolarApiClient $api): int
    {
        // Covers manual invocations as well as the scheduler. Production must
        // use a shared cache and enforce a runtime shorter than this lock lease.
        $lock = Cache::lock('alerts:send-visibility', 3600);
        if (! $lock->get()) {
            $this->info('Another visibility alert run is already active.');

            return self::SUCCESS;
        }

        try {
            return $this->sendAlerts($api);
        } finally {
            $lock->release();
        }
    }

    private function sendAlerts(SolarApiClient $api): int
    {
        $failed = 0;
        $sent = 0;
        $checked = 0;

        VisibilityAlert::query()
            ->where('active', true)
            ->with('user')
            ->chunkById(100, function ($alerts) use ($api, &$sent, &$checked, &$failed): void {
                foreach ($alerts as $alert) {
                    $checked++;

                    try {
                        $sky = $api->sky(
                            $alert->object_id,
                            null,
                            (float) $alert->latitude,
                            (float) $alert->longitude,
                        );
                    } catch (SolarApiException) {
                        continue;
                    }

                    $observer = $sky?->observer;
                    $objectName = $sky?->name;
                    // An unavailable observer calculation is not a transition
                    // below the horizon: preserve the previous notification state.
                    if ($observer === null) {
                        continue;
                    }

                    $upAfterDark = $observer->isUp && $observer->isDark;

                    if ($upAfterDark && $alert->last_state_up_after_dark !== true) {
                        try {
                            $alert->user->notify(new VisibilityUpAfterDarkNotification($alert, $objectName));
                        } catch (Throwable $exception) {
                            report($exception);
                            $failed++;

                            // Leave the transition pending for the next run and
                            // continue delivering other users' alerts.
                            continue;
                        }
                        $sent++;
                        $alert->last_triggered_at = now();
                    }

                    $alert->last_checked_at = now();
                    $alert->last_state_up_after_dark = $upAfterDark;
                    $alert->save();
                }
            });

        $this->info("Checked {$checked} alerts; sent {$sent} notifications.");

        if ($failed > 0) {
            $this->error("Failed to send {$failed} notifications; they remain pending.");
        }

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
