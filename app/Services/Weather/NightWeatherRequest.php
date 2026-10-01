<?php

declare(strict_types=1);

namespace App\Services\Weather;

use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;
use Throwable;

final readonly class NightWeatherRequest
{
    private function __construct(public float $lat, public float $lon, public CarbonImmutable $start, public CarbonImmutable $end) {}

    /** @param array<string, mixed> $input */
    public static function parse(array $input): self
    {
        if (array_diff(array_keys($input), ['lat', 'lon', 'window_start_utc', 'window_end_utc', '_token']) !== []) {
            throw ValidationException::withMessages(['weather' => 'Only coordinates and the selected UTC interval are accepted.']);
        }
        $coordinates = [];
        foreach (['lat' => 90, 'lon' => 180] as $field => $limit) {
            $value = $input[$field] ?? null;
            if ((! is_int($value) && ! is_float($value) && ! (is_string($value) && preg_match('/^-?(?:[0-9]+)(?:\.[0-9]+)?$/D', $value)))
                || ! is_finite((float) $value) || abs((float) $value) > $limit) {
                throw ValidationException::withMessages([$field => 'Supply a finite coordinate within its geographic range.']);
            }
            $coordinates[$field] = round((float) $value, 2);
        }
        $start = self::utc($input['window_start_utc'] ?? null);
        $end = self::utc($input['window_end_utc'] ?? null);
        if ($end <= $start || $end->getTimestamp() - $start->getTimestamp() > 26 * 3600) {
            throw ValidationException::withMessages(['weather' => 'Choose a positive UTC interval of at most 26 hours.']);
        }

        return new self($coordinates['lat'], $coordinates['lon'], $start, $end);
    }

    private static function utc(mixed $value): CarbonImmutable
    {
        if (is_string($value) && preg_match('/^[0-9]{4}-[0-9]{2}-[0-9]{2}T[0-9]{2}:[0-9]{2}:[0-9]{2}Z$/D', $value)) {
            try {
                $date = CarbonImmutable::createFromFormat('!Y-m-d\TH:i:s\Z', $value, 'UTC');
                if ($date && $date->year >= 1 && $date->toIso8601ZuluString() === $value) {
                    return $date;
                }
            } catch (Throwable) {
                // Invalid calendar dates are validation failures, never rolled forward.
            }
        }
        throw ValidationException::withMessages(['weather' => 'Supply real UTC timestamps in YYYY-MM-DDTHH:MM:SSZ format.']);
    }
}
