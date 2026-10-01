<?php

declare(strict_types=1);

namespace App\Services\Weather;

use GuzzleHttp\Psr7\Stream;
use RuntimeException;

/** Bounds decoded response bytes, including compressed HTTP responses. */
final class BoundedWeatherStream extends Stream
{
    public const int MAX_BYTES = 65536;

    private int $written = 0;

    public function __construct()
    {
        $resource = fopen('php://temp', 'w+');
        if ($resource === false) {
            throw new RuntimeException('Forecast response storage is unavailable.');
        }
        parent::__construct($resource);
    }

    public function write(string $string): int
    {
        if (strlen($string) > self::MAX_BYTES - $this->written) {
            throw new RuntimeException('Forecast response exceeded its size limit.');
        }
        $count = parent::write($string);
        $this->written += $count;

        return $count;
    }
}
