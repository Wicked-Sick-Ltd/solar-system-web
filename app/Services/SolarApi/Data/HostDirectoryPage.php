<?php

declare(strict_types=1);

namespace App\Services\SolarApi\Data;

final readonly class HostDirectoryPage
{
    /** @param list<MeasuredHost> $hosts */
    public function __construct(public array $hosts, public int $total, public int $pages) {}
}
