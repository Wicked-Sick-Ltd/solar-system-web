<?php

declare(strict_types=1);

namespace App\Services\SolarApi\Data;

final readonly class StarterPage
{
    /**
     * @param  list<StarterTarget>  $targets
     * @param  array<string,StarterSource>  $sources
     */
    public function __construct(public array $targets, public int $total, public array $sources) {}
}
