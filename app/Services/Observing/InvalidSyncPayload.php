<?php

declare(strict_types=1);

namespace App\Services\Observing;

final class InvalidSyncPayload extends \RuntimeException
{
    public function __construct()
    {
        parent::__construct('The observing data is not a supported complete document.');
    }
}
