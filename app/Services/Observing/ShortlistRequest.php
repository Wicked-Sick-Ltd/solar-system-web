<?php

declare(strict_types=1);

namespace App\Services\Observing;

use Illuminate\Validation\ValidationException;

final class ShortlistRequest
{
    public const MODES = ['naked_eye', 'binocular', 'telescope'];

    public const PREFERENCES = ['balanced', 'wide_field', 'stars', 'deep_sky', 'solar_system'];

    public const OPTION_FIELDS = ['equipment_mode', 'preference', 'true_field_deg', 'max_catalogue_v_magnitude', 'shortlist_limit'];

    /** @param array<string,mixed> $input
     * @return array<string,mixed>
     */
    public static function parse(array $input): array
    {
        if (array_diff(array_keys($input), [...NightRequest::CONDITION_FIELDS, ...self::OPTION_FIELDS, '_token']) !== []) {
            throw ValidationException::withMessages(['preferences' => 'An unsupported shortlist field was supplied.']);
        }
        $mode = $input['equipment_mode'] ?? null;
        $preference = $input['preference'] ?? 'balanced';
        if (! in_array($mode, self::MODES, true)) {
            throw ValidationException::withMessages(['equipment_mode' => 'Choose an equipment mode.']);
        }
        if (! in_array($preference, self::PREFERENCES, true)) {
            throw ValidationException::withMessages(['preference' => 'Choose a supported shortlist preference.']);
        }
        $limit = $input['shortlist_limit'] ?? 6;
        if ((! is_int($limit) && (! is_string($limit) || preg_match('/\A[1-8]\z/D', $limit) !== 1)) || (int) $limit < 1 || (int) $limit > 8) {
            throw ValidationException::withMessages(['shortlist_limit' => 'Choose one to eight shortlisted targets.']);
        }
        $options = ['equipment_mode' => $mode, 'preference' => $preference, 'shortlist_limit' => (int) $limit];
        foreach (['true_field_deg' => [0.01, 180], 'max_catalogue_v_magnitude' => [-30, 30]] as $key => [$min, $max]) {
            $value = $input[$key] ?? null;
            if ($value === null || $value === '') {
                $options[$key] = null;
            } elseif ((! is_string($value) && ! is_int($value) && ! is_float($value)) || ! is_numeric($value)
                || ! is_finite((float) $value) || $value < $min || $value > $max) {
                throw ValidationException::withMessages([$key => 'Supply a finite value between '.$min.' and '.$max.', or leave this optional field blank.']);
            } else {
                $options[$key] = (float) $value;
            }
        }

        return NightRequest::conditions($input) + $options;
    }
}
