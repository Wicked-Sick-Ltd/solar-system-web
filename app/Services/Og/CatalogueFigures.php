<?php

declare(strict_types=1);

namespace App\Services\Og;

use App\Services\SolarApi\Exceptions\SolarApiException;
use App\Services\SolarApi\SolarApiClient;
use App\Support\Format;

/** Headline catalogue counts for the site share card, read from the cached API. */
final class CatalogueFigures
{
    public function __construct(private readonly SolarApiClient $api) {}

    /**
     * Only counts the backend actually reported; an unreachable backend yields
     * an empty list so the card renders without figures rather than with zeros.
     *
     * @return list<array{value: string, label: string}>
     */
    public function all(): array
    {
        $figures = [];

        try {
            $stats = $this->api->stats();
        } catch (SolarApiException) {
            $stats = null;
        }

        if ($stats !== null && $stats->totalObjects > 0) {
            $figures[] = ['value' => Format::count($stats->totalObjects), 'label' => __('Solar-system objects')];
            if ($stats->moons() > 0) {
                $figures[] = ['value' => Format::count($stats->moons()), 'label' => __('Moons')];
            }
        }

        try {
            $exoplanets = $this->api->exoplanetCount();
        } catch (SolarApiException) {
            $exoplanets = null;
        }

        if ($exoplanets !== null && $exoplanets > 0) {
            $figures[] = ['value' => Format::count($exoplanets), 'label' => __('Exoplanets')];
        }

        return $figures;
    }
}
