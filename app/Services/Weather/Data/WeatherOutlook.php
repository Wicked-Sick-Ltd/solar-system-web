<?php

declare(strict_types=1);

namespace App\Services\Weather\Data;

final readonly class WeatherOutlook
{
    public function __construct(
        public string $bestHourUtc,
        public int $cloudCoverPercent,
        public string $verdict,
        public string $dewRisk,
        public ?int $visibilityMetres,
        public ?float $windSpeedMS,
        public ?int $humidityPercent,
    ) {}
}
