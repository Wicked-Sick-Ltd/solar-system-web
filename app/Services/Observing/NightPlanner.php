<?php

declare(strict_types=1);

namespace App\Services\Observing;

final class NightPlanner
{
    public function __construct(private readonly NightTransport $transport) {}

    /** @param array<string,mixed> $query
     * @return array<string,mixed>
     */
    public function calculate(array $query): array
    {
        return NightPlan::validate($this->transport->send('night', $query), $query);
    }
}
