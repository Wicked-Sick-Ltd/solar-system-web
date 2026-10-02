<?php

declare(strict_types=1);

namespace App\Services\Observing;

use App\Services\SolarApi\Exceptions\SolarApiException;
use DateTimeImmutable;
use DateTimeZone;

/** Fail closed at the API boundary before rendering scientific results. */
final class NightPlan
{
    /** @param array<string,mixed> $request
     * @return array<string,mixed>
     */
    public static function validate(mixed $data, array $request): array
    {
        self::require(is_array($data) && ($data['schema_version'] ?? null) === 1);
        foreach (['observer', 'night', 'constraints', 'method', 'darkness', 'moon'] as $key) {
            self::require(is_array($data[$key] ?? null));
        }
        $observer = $data['observer'];
        self::require(($observer['timezone'] ?? null) === $request['timezone']);
        foreach (['lat', 'lon'] as $key) {
            self::number($observer[$key] ?? null, -180, 180);
            self::require(abs($observer[$key] - $request[$key]) < 0.000001);
        }
        self::require(($observer['elevation_m'] ?? null) === 0);
        $night = $data['night'];
        self::require(($night['date'] ?? null) === $request['date']);
        $start = self::instant($night['start_utc'] ?? null);
        $end = self::instant($night['end_utc'] ?? null);
        $zone = new DateTimeZone($request['timezone']);
        $localStart = new DateTimeImmutable($request['date'].' 12:00:00', $zone);
        self::require($start === $localStart->getTimestamp() && $end === $localStart->modify('+1 day')->getTimestamp());
        self::number($night['duration_hours'] ?? null, 22, 26);
        self::require(abs($night['duration_hours'] * 3600 - ($end - $start)) < 0.01);
        foreach (['min_altitude_deg', 'sun_altitude_deg', 'min_moon_separation_deg'] as $key) {
            self::number($data['constraints'][$key] ?? null, -180, 180);
            self::require(abs($data['constraints'][$key] - $request[$key]) < 0.000001);
        }
        self::number($data['constraints']['min_sun_separation_deg'] ?? null, 30, 30);
        self::label($data['constraints']['moon_separation_rule'] ?? null);
        $method = $data['method'];
        foreach (['provider', 'ephemeris', 'frame', 'refraction', 'accuracy_note', 'window_note'] as $key) {
            self::label($method[$key] ?? null);
        }
        self::number($method['sample_minutes'] ?? null, 0.1, 5);
        self::number($method['root_tolerance_seconds'] ?? null, 0.001, 60);
        self::require(is_array($method['iers'] ?? null));
        self::require(in_array($method['iers']['status'] ?? null, ['measured', 'predicted'], true));
        self::require(self::instant($method['iers']['start_utc'] ?? null) <= $start);
        self::require(self::instant($method['iers']['end_utc'] ?? null) >= $end);
        self::windows($data['darkness']['intervals'] ?? null, $start, $end);
        self::require(in_array($data['darkness']['status'] ?? null, ['unresolved_grazing', 'intervals_found', 'no_matching_interval'], true));
        self::status($data['darkness']['status'], $data['darkness']['intervals'], 'intervals_found', 'no_matching_interval');
        $moon = $data['moon'];
        self::number($moon['illumination_fraction'] ?? null, 0, 1);
        self::number($moon['phase_angle_deg'] ?? null, 0, 180);
        self::number($moon['elongation_deg'] ?? null, 0, 180);
        self::label($moon['definition'] ?? null);
        $reference = self::instant($moon['reference_utc'] ?? null);
        self::require($reference >= $start && $reference <= $end);
        self::samples($moon['samples'] ?? null, $start, $end, $method['sample_minutes']);
        self::require(is_array($data['targets'] ?? null) && array_is_list($data['targets']));
        self::require(count($data['targets']) === count(explode(',', $request['targets'])));
        $ids = [];
        foreach ($data['targets'] as $target) {
            self::require(is_array($target));
            self::require(in_array($target['id'] ?? null, NightRequest::TARGETS, true));
            $ids[] = $target['id'];
            self::label($target['name'] ?? null);
            self::require(in_array($target['status'] ?? null, ['unresolved_grazing', 'windows_found', 'no_matching_window'], true));
            self::windows($target['windows'] ?? null, $start, $end);
            self::status($target['status'], $target['windows'], 'windows_found', 'no_matching_window');
            self::samples($target['samples'] ?? null, $start, $end, $method['sample_minutes']);
            self::require(array_column($target['samples'], 'time_utc') === array_column($moon['samples'], 'time_utc'));
        }
        self::require(implode(',', $ids) === $request['targets']);

        return $data;
    }

    private static function require(bool $condition): void
    {
        if (! $condition) {
            throw new SolarApiException('The night-planning response could not be validated.');
        }
    }

    private static function number(mixed $value, float $min, float $max): void
    {
        self::require((is_int($value) || is_float($value)) && is_finite((float) $value) && $value >= $min && $value <= $max);
    }

    private static function label(mixed $value): void
    {
        self::require(is_string($value) && trim($value) !== '' && strlen($value) <= 2000);
    }

    private static function instant(mixed $value): int
    {
        self::require(is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/D', $value) === 1);
        $instant = DateTimeImmutable::createFromFormat('!Y-m-d\TH:i:s\Z', $value, new DateTimeZone('UTC'));
        self::require($instant !== false && $instant->format('Y-m-d\TH:i:s\Z') === $value);

        return $instant->getTimestamp();
    }

    private static function windows(mixed $windows, int $start, int $end): void
    {
        self::require(is_array($windows) && array_is_list($windows) && count($windows) <= 64);
        $previous = $start;
        foreach ($windows as $window) {
            self::require(is_array($window));
            $a = self::instant($window['start_utc'] ?? null);
            $b = self::instant($window['end_utc'] ?? null);
            self::require($a >= $previous && $b > $a && $b <= $end);
            $previous = $b;
        }
    }

    /** @param list<array<string,mixed>> $windows */
    private static function status(string $status, array $windows, string $found, string $none): void
    {
        self::require($status !== $found || count($windows) > 0);
        self::require($status !== $none || count($windows) === 0);
    }

    private static function samples(mixed $samples, int $start, int $end, float $minutes): void
    {
        self::require(is_array($samples) && array_is_list($samples) && count($samples) >= 2 && count($samples) <= 400);
        $previous = null;
        foreach ($samples as $sample) {
            self::require(is_array($sample));
            $t = self::instant($sample['time_utc'] ?? null);
            self::require($t >= $start && $t <= $end && ($previous === null || ($t > $previous && $t - $previous <= $minutes * 60 + 1)));
            foreach (['altitude_deg', 'sun_altitude_deg', 'moon_altitude_deg'] as $key) {
                self::number($sample[$key] ?? null, -90, 90);
            }
            self::number($sample['azimuth_deg'] ?? null, 0, 360);
            foreach (['sun_separation_deg', 'moon_separation_deg'] as $key) {
                self::number($sample[$key] ?? null, 0, 180);
            }
            self::number($sample['distance_au'] ?? null, 0.000001, 1000);
            $previous = $t;
        }
        self::require(self::instant($samples[0]['time_utc']) === $start && $previous === $end);
    }
}
