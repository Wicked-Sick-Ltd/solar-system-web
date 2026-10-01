<?php

declare(strict_types=1);

namespace App\Services\Observing;

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

final class NightRequest
{
    public const TARGETS = ['moon', 'mercury', 'venus', 'mars', 'jupiter', 'saturn', 'uranus', 'neptune'];

    /** @param array<string,mixed> $input
     * @return array<string,mixed>
     */
    public static function parse(array $input): array
    {
        if (array_diff(array_keys($input), ['_token', 'date', 'timezone', 'lat', 'lon', 'targets',
            'min_altitude_deg', 'sun_altitude_deg', 'min_moon_separation_deg', 'window_start_utc', 'window_end_utc', 'horizon']) !== []) {
            throw ValidationException::withMessages(['constraints' => 'An unsupported planning field was supplied.']);
        }
        $data = Validator::make($input, [
            'date' => ['required', 'date_format:Y-m-d', 'after_or_equal:1900-01-01', 'before_or_equal:2100-12-31'],
            'timezone' => ['required', 'string', 'max:100', 'timezone:all'],
            'lat' => ['required', 'numeric', 'between:-90,90'],
            'lon' => ['required', 'numeric', 'between:-180,180'],
            'targets' => ['required', 'array', 'list', 'min:1', 'max:8'],
            'targets.*' => ['required', 'string', 'distinct:strict', Rule::in(self::TARGETS)],
            'min_altitude_deg' => ['required', 'numeric', 'between:0,90'],
            'sun_altitude_deg' => ['required', Rule::in([-6, -12, -18])],
            'min_moon_separation_deg' => ['required', 'numeric', 'between:0,180'],
        ])->validate();

        $query = [
            'date' => $data['date'], 'timezone' => $data['timezone'],
            'lat' => round((float) $data['lat'], 2), 'lon' => round((float) $data['lon'], 2),
            'targets' => implode(',', $data['targets']),
            'min_altitude_deg' => (float) $data['min_altitude_deg'],
            'sun_altitude_deg' => (float) $data['sun_altitude_deg'],
            'min_moon_separation_deg' => (float) $data['min_moon_separation_deg'],
            'window_start_utc' => ($input['window_start_utc'] ?? '') === '' ? null : ($input['window_start_utc'] ?? null),
            'window_end_utc' => ($input['window_end_utc'] ?? '') === '' ? null : ($input['window_end_utc'] ?? null),
            'horizon_mask' => NightConstraints::horizon($input['horizon'] ?? null),
        ];
        NightConstraints::window($query);

        return $query;
    }
}
