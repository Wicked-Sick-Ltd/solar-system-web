<?php

declare(strict_types=1);

namespace App\Services\Observing;

use App\Services\SolarApi\Exceptions\SolarApiException;
use DateTimeImmutable;
use DateTimeZone;

/** Small validation primitives for the versioned planning provenance contract. */
final class NightMetadata
{
    /** @param list<string> $keys
     * @return array<string,mixed>
     */
    public static function shape(mixed $value, array $keys): array
    {
        self::require(is_array($value) && ! array_is_list($value));
        self::require(count($value) === count($keys) && array_diff($keys, array_keys($value)) === []);

        return $value;
    }

    public static function require(bool $condition): void
    {
        if (! $condition) {
            throw new SolarApiException('The night-planning provenance could not be validated.');
        }
    }

    public static function text(mixed $value, int $max = 2000): string
    {
        self::require(is_string($value) && trim($value) !== '' && strlen($value) <= $max
            && preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', $value) === 0);

        return $value;
    }

    public static function number(mixed $value, float $min = -PHP_FLOAT_MAX, float $max = PHP_FLOAT_MAX): float
    {
        self::require((is_int($value) || is_float($value)) && is_finite((float) $value) && $value >= $min && $value <= $max);

        return (float) $value;
    }

    public static function hash(mixed $value): void
    {
        self::require(is_string($value) && preg_match('/^[a-f0-9]{64}$/D', $value) === 1);
    }

    public static function url(mixed $value): void
    {
        $value = self::text($value, 8192);
        self::require(filter_var($value, FILTER_VALIDATE_URL) !== false && parse_url($value, PHP_URL_SCHEME) === 'https'
            && parse_url($value, PHP_URL_USER) === null && parse_url($value, PHP_URL_PASS) === null);
    }

    public static function version(mixed $value): void
    {
        self::require(is_string($value) && preg_match('/^[0-9][A-Za-z0-9.+!_-]{0,79}$/D', $value) === 1);
    }

    public static function date(mixed $value): void
    {
        self::require(is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2}$/D', $value) === 1);
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value, new DateTimeZone('UTC'));
        self::require($date !== false && $date->format('Y-m-d') === $value);
    }

    public static function utc(mixed $value): int
    {
        self::require(is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/D', $value) === 1);
        $time = DateTimeImmutable::createFromFormat('!Y-m-d\TH:i:s\Z', $value, new DateTimeZone('UTC'));
        self::require($time !== false && $time->format('Y-m-d\TH:i:s\Z') === $value);

        return $time->getTimestamp();
    }

    /** Validate TDB calendar fields without reinterpreting TDB as UTC. */
    public static function tdb(mixed $value): string
    {
        self::require(is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}\.\d{3}$/D', $value) === 1);
        $calendar = DateTimeImmutable::createFromFormat('!Y-m-d\TH:i:s.v', $value, new DateTimeZone('UTC'));
        self::require($calendar !== false && $calendar->format('Y-m-d\TH:i:s.v') === $value);

        return $value;
    }

    public static function retrieval(mixed $value): void
    {
        $value = self::text($value, 40);
        self::require(preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:\.\d{1,6})?(?:Z|[+-](?:[01]\d|2[0-3]):[0-5]\d)$/D', $value) === 1);
        try {
            $time = new DateTimeImmutable($value);
            self::require($time->format('Y-m-d\TH:i:s') === substr($value, 0, 19));
        } catch (\Exception) {
            throw new SolarApiException('The catalogue retrieval timestamp is invalid.');
        }
    }
}
