<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Services\SolarApi\Data\CloseApproach;
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
            $approaches = $this->dedupeNearby($approaches);
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

    /**
     * The catalogue sometimes lists one encounter twice, a minute apart.
     * Collapse the same designation within ten minutes and keep the fuller row.
     *
     * @param  list<CloseApproach>  $approaches
     * @return list<CloseApproach>
     */
    private function dedupeNearby(array $approaches): array
    {
        $groups = [];
        foreach ($approaches as $approach) {
            $groups[$this->approachKey($approach)][] = $approach;
        }

        $kept = [];
        foreach ($groups as $rows) {
            usort($rows, fn (CloseApproach $a, CloseApproach $b): int => strcmp((string) $a->cdIso, (string) $b->cdIso));
            $cluster = [];
            $anchor = null;
            foreach ($rows as $row) {
                $time = CarbonImmutable::parse((string) $row->cdIso)->getTimestamp();
                if ($anchor !== null && abs($time - $anchor) > 600) {
                    $kept[] = $this->mostComplete($cluster);
                    $cluster = [];
                    $anchor = null;
                }
                if ($anchor === null) {
                    $anchor = $time;
                }
                $cluster[] = $row;
            }
            $kept[] = $this->mostComplete($cluster);
        }

        usort($kept, fn (CloseApproach $a, CloseApproach $b): int => strcmp((string) $a->cdIso, (string) $b->cdIso));

        return $kept;
    }

    private function approachKey(CloseApproach $approach): string
    {
        $designation = trim((string) $approach->designation);
        if ($designation !== '') {
            return 'd:'.$designation;
        }

        $name = trim((string) $approach->name);
        if ($name !== '') {
            return 'n:'.$name;
        }

        return 'id:'.trim((string) $approach->objectId);
    }

    /** @param  list<CloseApproach>  $rows */
    private function mostComplete(array $rows): CloseApproach
    {
        $best = $rows[0];
        $bestScore = $this->completeness($best);
        foreach ($rows as $row) {
            $score = $this->completeness($row);
            if ($score > $bestScore) {
                $best = $row;
                $bestScore = $score;
            }
        }

        return $best;
    }

    private function completeness(CloseApproach $approach): int
    {
        $score = 0;
        foreach ([
            $approach->distAu,
            $approach->distMinAu,
            $approach->distMaxAu,
            $approach->vRelKmS,
            $approach->massKg,
            $approach->tSigma,
            $approach->name,
            $approach->objectId,
        ] as $value) {
            if ($value !== null && $value !== '') {
                $score++;
            }
        }

        return $score;
    }
}
