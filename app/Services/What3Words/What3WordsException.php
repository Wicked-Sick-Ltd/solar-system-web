<?php

declare(strict_types=1);

namespace App\Services\What3Words;

use RuntimeException;

final class What3WordsException extends RuntimeException
{
    /**
     * @param  'invalid'|'unrecognised'|'unavailable'|'disabled'  $kind
     */
    public function __construct(string $message, public readonly string $kind = 'unavailable')
    {
        parent::__construct($message);
    }
}
