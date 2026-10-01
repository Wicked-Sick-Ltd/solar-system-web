<?php

declare(strict_types=1);

namespace App\Services\Observing;

use App\Services\SolarApi\Exceptions\SolarApiException;
use GuzzleHttp\Psr7\Stream;

/** Stops Guzzle's decoded response writes before the body exceeds its bound. */
final class BoundedResponseStream extends Stream
{
    public const int MAX_BYTES = 1500000;

    private int $written = 0;

    public function __construct()
    {
        $resource = fopen('php://temp', 'w+');
        if ($resource === false) {
            throw new SolarApiException('Night planning response storage is unavailable.');
        }
        parent::__construct($resource);
    }

    public function write(string $string): int
    {
        if (strlen($string) > self::MAX_BYTES - $this->written) {
            throw new SolarApiException('Night planning response exceeded its size limit.');
        }
        $count = parent::write($string);
        $this->written += $count;

        return $count;
    }
}
