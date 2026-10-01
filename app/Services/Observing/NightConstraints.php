<?php

declare(strict_types=1);

namespace App\Services\Observing;

use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Validation\ValidationException;

final class NightConstraints
{
    /** @return list<array{azimuth_deg:float,min_altitude_deg:float}>|null */
    public static function horizon(mixed $text): ?array
    {
        if ($text === null || $text === '') {
            return null;
        }
        if (! is_string($text) || strlen($text) > 5000) {
            throw ValidationException::withMessages(['horizon' => 'Use at most 72 lines of azimuth and minimum altitude, in degrees.']);
        }
        $lines = preg_split('/\R/', trim($text));
        $points = [];
        foreach ($lines ?: [] as $line) {
            if (trim($line) === '') {
                continue;
            }
            $parts = preg_split('/[\s,]+/', trim($line));
            if (count($parts ?: []) !== 2 || ! is_numeric($parts[0]) || ! is_numeric($parts[1])) {
                throw ValidationException::withMessages(['horizon' => 'Each horizon line needs two numbers: azimuth and minimum altitude, in degrees.']);
            }
            [$azimuth, $altitude] = array_map('floatval', $parts);
            if (! is_finite($azimuth) || ! is_finite($altitude) || $azimuth < 0 || $azimuth > 360 || $altitude < -90 || $altitude > 90) {
                throw ValidationException::withMessages(['horizon' => 'Horizon azimuth must be 0–360° and altitude −90–90°.']);
            }
            $azimuth = $azimuth === 360.0 ? 0.0 : $azimuth;
            if (in_array($azimuth, array_column($points, 'azimuth_deg'), true)) {
                throw ValidationException::withMessages(['horizon' => 'Horizon azimuths must be distinct; 0° and 360° are the same direction.']);
            }
            $points[] = ['azimuth_deg' => $azimuth, 'min_altitude_deg' => $altitude];
        }
        if ($points === []) {
            return null;
        }
        if (count($points) < 2 || count($points) > 72) {
            throw ValidationException::withMessages(['horizon' => 'Supply between 2 and 72 distinct horizon directions, or leave the horizon blank.']);
        }
        usort($points, static fn (array $a, array $b): int => $a['azimuth_deg'] <=> $b['azimuth_deg']);

        return $points;
    }

    /** @param array<string,mixed> $request
     * @return array{0:string,1:string}
     */
    public static function window(array $request): array
    {
        $zone = new DateTimeZone($request['timezone']);
        $start = new DateTimeImmutable($request['date'].' 12:00:00', $zone);
        $end = $start->modify('+1 day');
        $format = static fn (DateTimeImmutable $instant): string => $instant->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d\TH:i:s\Z');
        $a = $request['window_start_utc'] ?? null;
        $b = $request['window_end_utc'] ?? null;
        if ($a === null && $b === null) {
            return [$format($start), $format($end)];
        }
        $first = self::instant($a);
        $last = self::instant($b);
        if ($first === null || $last === null || $first < $start || $last > $end || $last <= $first) {
            throw ValidationException::withMessages(['window_start_utc' => 'Provide both real UTC times in YYYY-MM-DDTHH:MM:SSZ format, ordered within this local-noon to next-noon night.']);
        }

        return [$a, $b];
    }

    private static function instant(mixed $raw): ?DateTimeImmutable
    {
        if (! is_string($raw) || preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/D', $raw) !== 1) {
            return null;
        }
        $time = DateTimeImmutable::createFromFormat('!Y-m-d\TH:i:s\Z', $raw, new DateTimeZone('UTC'));

        return $time !== false && $time->format('Y-m-d\TH:i:s\Z') === $raw ? $time : null;
    }

    /** @param list<array{azimuth_deg:float,min_altitude_deg:float}> $mask */
    public static function altitude(array $mask, float $azimuth): float
    {
        $azimuth = fmod($azimuth + 360, 360);
        foreach ($mask as $index => $left) {
            $right = $mask[($index + 1) % count($mask)];
            $end = $right['azimuth_deg'] + ($index === count($mask) - 1 ? 360 : 0);
            $at = $azimuth < $left['azimuth_deg'] ? $azimuth + 360 : $azimuth;
            if ($at >= $left['azimuth_deg'] && $at <= $end) {
                return $left['min_altitude_deg'] + ($right['min_altitude_deg'] - $left['min_altitude_deg'])
                    * ($at - $left['azimuth_deg']) / ($end - $left['azimuth_deg']);
            }
        }
        throw new \LogicException('Canonical horizon did not cover the azimuth.');
    }
}
