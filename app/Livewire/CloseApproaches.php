<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Services\SolarApi\Exceptions\SolarApiException;
use App\Services\SolarApi\SolarApiClient;
use App\Support\Seo;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

/** Asteroids and comets passing Earth in the coming weeks, soonest first. */
#[Layout('components.layouts.app')]
final class CloseApproaches extends Component
{
    private const DAYS = 60;

    private const LIMIT = 200;

    public function render(SolarApiClient $api): View
    {
        app(Seo::class)
            ->title(__('Close approaches'))
            ->description(__('Asteroids and comets passing near Earth in the next :days days, from JPL close-approach data.', ['days' => self::DAYS]));

        $today = CarbonImmutable::now('UTC')->startOfDay();
        $apiDown = false;
        $approaches = [];
        $received = 0;

        try {
            $approaches = $api->closeApproaches($today->toDateString(), $today->addDays(self::DAYS)->toDateString(), limit: self::LIMIT);
            usort($approaches, fn ($a, $b) => strcmp((string) $a->cdIso, (string) $b->cdIso));
            $received = count($approaches);
        } catch (SolarApiException) {
            $apiDown = true;
        }

        return view('livewire.close-approaches', [
            'approaches' => $approaches,
            'apiDown' => $apiDown,
            'days' => self::DAYS,
            // Measured masses are rare for small bodies; only show the column when one is on record.
            'showMass' => array_any($approaches, fn ($a) => $a->massKg !== null),
            'limitReached' => $received >= self::LIMIT,
            'limit' => self::LIMIT,
            'windowStart' => $today->toDateString(),
            'windowEnd' => $today->addDays(self::DAYS)->toDateString(),
        ]);
    }
}
