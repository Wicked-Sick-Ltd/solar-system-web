<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Services\SolarApi\Data\Position;
use App\Services\SolarApi\SolarApiClient;
use App\Support\Seo;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * A 2D top-down orrery: the inner solar system out to Pluto, positioned for a
 * chosen date from the backend's /positions endpoint. A flourish, kept light —
 * positions come from one concurrent batch and the drawing is plain SVG.
 *
 * Distances use a square-root radial scale so Mercury and Pluto are both
 * legible on one canvas; it is deliberately not to scale.
 */
#[Layout('components.layouts.app')]
final class Orrery extends Component
{
    /**
     * The bodies plotted, in orbit order, with a representative colour for
     * rendering (the /positions endpoint carries no colour, and this is a
     * fixed curated set, so colours live here rather than costing extra calls).
     *
     * @var array<string,array{label:string,colour:string}>
     */
    private const BODIES = [
        'planet-mercury' => ['label' => 'Mercury', 'colour' => '#9b8a7a'],
        'planet-venus' => ['label' => 'Venus', 'colour' => '#e8cda2'],
        'planet-earth' => ['label' => 'Earth', 'colour' => '#6b93d6'],
        'planet-mars' => ['label' => 'Mars', 'colour' => '#c1440e'],
        'planet-jupiter' => ['label' => 'Jupiter', 'colour' => '#d8ca9d'],
        'planet-saturn' => ['label' => 'Saturn', 'colour' => '#ead6a0'],
        'planet-uranus' => ['label' => 'Uranus', 'colour' => '#b9e4e7'],
        'planet-neptune' => ['label' => 'Neptune', 'colour' => '#5b76e0'],
        'dwarf-ceres' => ['label' => 'Ceres', 'colour' => '#8c8377'],
        'dwarf-pluto' => ['label' => 'Pluto', 'colour' => '#c9b8a0'],
    ];

    #[Url(as: 'date', except: '')]
    public mixed $date = '';

    public function mount(): void
    {
        // Keep malformed query types until validation; never reinterpret a relative
        // date or silently roll an impossible calendar date into another month.
        if (request()->query->has('date')) {
            $this->date = request()->query()['date'] ?? '';
        } elseif ($this->date === '') {
            $this->today();
        }
    }

    public function today(): void
    {
        $this->date = CarbonImmutable::now('UTC')->toDateString();
    }

    public function step(int $days): void
    {
        if (($date = $this->dateAfter($days)) !== null) {
            $this->date = $date;
        }
    }

    public function render(SolarApiClient $api): View
    {
        app(Seo::class)
            ->title(__('Orrery'))
            ->description(__('An approximate 2D model of solar-system positions for a selected date, using catalogue orbital elements.'));

        $validDate = $this->validatedDate();
        $apiDown = $validDate !== null && ! $api->reachable();
        $bodies = [];

        if ($validDate !== null && ! $apiDown) {
            $positions = $api->positionsBatch(array_keys(self::BODIES), $validDate->toDateString());
            $bodies = $this->plot($positions);
        }

        return view('livewire.orrery', [
            'apiDown' => $apiDown,
            'bodies' => $bodies,
            'dateValue' => is_string($this->date) ? $this->date : '',
            'invalidDate' => $validDate === null,
            'prettyDate' => $validDate?->isoFormat('D MMMM YYYY'),
            'stepDates' => [-30 => $this->dateAfter(-30), -1 => $this->dateAfter(-1), 1 => $this->dateAfter(1), 30 => $this->dateAfter(30)],
            'expectedBodies' => count(self::BODIES),
            'missingBodies' => array_values(array_map(fn ($id) => self::BODIES[$id]['label'], array_diff(array_keys(self::BODIES), array_column($bodies, 'slug')))),
        ]);
    }

    /**
     * Turn positions into SVG-space points on a 600×600 canvas.
     *
     * @param  array<string,?Position>  $positions
     * @return list<array{slug:string,label:string,colour:string,cx:float,cy:float,r:float,distance:float}>
     */
    private function plot(array $positions): array
    {
        $centre = 300.0;
        $maxRadius = 270.0;

        // Scale to the furthest body we actually got a position for.
        $usable = array_filter($positions, static fn (?Position $position): bool => $position !== null
            && $position->xAu !== null && is_finite($position->xAu)
            && $position->yAu !== null && is_finite($position->yAu)
            && $position->distanceFromSunAu !== null && is_finite($position->distanceFromSunAu)
            && $position->distanceFromSunAu > 0);
        $maxDistance = 0.0;
        foreach ($usable as $position) {
            $maxDistance = max($maxDistance, $position->distanceFromSunAu);
        }
        if ($maxDistance <= 0.0) {
            return [];
        }

        $bodies = [];
        foreach (self::BODIES as $id => $meta) {
            $position = $usable[$id] ?? null;
            if ($position === null) {
                continue;
            }

            // Square-root radial scale spreads inner and outer worlds legibly.
            $radius = sqrt($position->distanceFromSunAu) / sqrt($maxDistance) * $maxRadius;
            $angle = atan2($position->yAu, $position->xAu);

            $bodies[] = [
                'slug' => $id,
                'label' => $meta['label'],
                'colour' => $meta['colour'],
                'cx' => round($centre + $radius * cos($angle), 2),
                'cy' => round($centre - $radius * sin($angle), 2), // flip y for screen space
                'r' => round($radius, 2),
                'distance' => $position->distanceFromSunAu,
            ];
        }

        return $bodies;
    }

    private function validatedDate(): ?CarbonImmutable
    {
        if (! is_string($this->date) || ! preg_match('/^[0-9]{4}-[0-9]{2}-[0-9]{2}$/D', $this->date)
            || (int) substr($this->date, 0, 4) < 1) {
            return null;
        }
        try {
            $date = CarbonImmutable::createFromFormat('!Y-m-d', $this->date, 'UTC');

            return $date && $date->toDateString() === $this->date ? $date : null;
        } catch (\Throwable) {
            return null;
        }
    }

    private function dateAfter(int $days): ?string
    {
        $date = $this->validatedDate();
        if ($date === null || $days < -36600 || $days > 36600) {
            return null;
        }
        $next = $date->addDays($days);

        return $next->year >= 1 && $next->year <= 9999 ? $next->toDateString() : null;
    }
}
