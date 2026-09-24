<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\VisibilityAlert;
use App\Notifications\VisibilityUpAfterDarkNotification;
use App\Services\SolarApi\Exceptions\SolarApiException;
use App\Services\SolarApi\SolarApiClient;
use Illuminate\Console\Command;

final class SendVisibilityAlerts extends Command
{
    protected $signature = 'alerts:send-visibility';

    protected $description = 'Email users when a saved object is up after dark from their saved location';

    public function handle(SolarApiClient $api): int
    {
        $sent = 0;
        $checked = 0;

        VisibilityAlert::query()
            ->where('active', true)
            ->with('user')
            ->chunkById(100, function ($alerts) use ($api, &$sent, &$checked): void {
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

                    $upAfterDark = ($sky?->observer?->isUp ?? false) && ($sky?->observer?->isDark ?? false);

                    if ($upAfterDark && $alert->last_state_up_after_dark !== true) {
                        $alert->user->notify(new VisibilityUpAfterDarkNotification($alert, $sky?->name));
                        $sent++;
                        $alert->last_triggered_at = now();
                    }

                    $alert->last_checked_at = now();
                    $alert->last_state_up_after_dark = $upAfterDark;
                    $alert->save();
                }
            });

        $this->info("Checked {$checked} alerts; sent {$sent} notifications.");

        return self::SUCCESS;
    }
}
