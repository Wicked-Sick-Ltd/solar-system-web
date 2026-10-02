<?php

declare(strict_types=1);

namespace App\Services\Observing;

final class ShortlistPlanner
{
    public function __construct(private readonly NightTransport $transport) {}

    /** @param array<string,mixed> $query
     * @return array<string,mixed>
     */
    public function calculate(array $query): array
    {
        return ShortlistPlan::validate($this->transport->send('discover', $query), $query);
    }
}
