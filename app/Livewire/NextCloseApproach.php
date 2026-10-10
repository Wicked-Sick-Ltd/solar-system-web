<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Services\SolarApi\Exceptions\SolarApiException;
use App\Services\SolarApi\SolarApiClient;
use App\Support\CloseApproachFormat;
use App\Support\UpcomingCloseApproach;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Livewire\Component;

/** Homepage card for the next Earth pass within 10 lunar distances. */
final class NextCloseApproach extends Component
{
    public function render(SolarApiClient $api): View
    {
        $now = CarbonImmutable::now('UTC');
        $from = $now->startOfDay();
        $apiDown = false;
        $approaches = [];

        try {
            // Same cached catalogue read as the close-approaches page, narrowed
            // to 10 lunar distances so "next" is not crowded out by nearer later passes.
            $approaches = $api->closeApproaches(
                $from->toDateString(),
                $from->addDays(UpcomingCloseApproach::WINDOW_DAYS)->toDateString(),
                maxDistAu: UpcomingCloseApproach::maxDistanceAu(),
                limit: UpcomingCloseApproach::LIMIT,
            );
        } catch (SolarApiException) {
            $apiDown = true;
        }

        $approach = $apiDown ? null : UpcomingCloseApproach::select($approaches, $now);
        $when = UpcomingCloseApproach::instant($approach?->cdIso);
        // The date-window row's object id is the catalogue key for its page.
        // Checking the detail endpoint as well would add a second homepage request.
        $objectUrl = $approach?->objectId ? route('objects.show', $approach->objectId) : null;

        return view('livewire.next-close-approach', [
            'apiDown' => $apiDown,
            'approach' => $approach,
            'objectUrl' => $objectUrl,
            'countdown' => $when ? UpcomingCloseApproach::countdown($now, $when) : null,
            'utcLabel' => $when ? UpcomingCloseApproach::utcLabel($when) : null,
            'phrases' => UpcomingCloseApproach::clockStrings(),
            'lunarDistance' => $approach ? CloseApproachFormat::measurement($approach->lunarDistances(), 'LD', 1) : null,
            'distanceKm' => $approach ? CloseApproachFormat::measurement($approach->distanceKm(), 'km', 0) : null,
            'velocity' => $approach && $approach->vRelKmS !== null
                ? CloseApproachFormat::measurement($approach->vRelKmS, 'km/s', 1)
                : null,
            'size' => $approach ? CloseApproachFormat::size($approach) : null,
            'capped' => ! $apiDown && count($approaches) >= UpcomingCloseApproach::LIMIT,
            'days' => UpcomingCloseApproach::WINDOW_DAYS,
            'limit' => UpcomingCloseApproach::LIMIT,
        ]);
    }
}
