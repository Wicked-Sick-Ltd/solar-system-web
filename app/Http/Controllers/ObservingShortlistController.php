<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\Observing\ShortlistPlanner;
use App\Services\Observing\ShortlistRequest;
use App\Services\SolarApi\Exceptions\SolarApiException;
use App\Support\Seo;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;

final class ObservingShortlistController
{
    public function __invoke(Request $request, ShortlistPlanner $planner): Response
    {
        app(Seo::class)->title(__('Find targets for a night'))->description(__('Request an explained shortlist from a bounded sample of bright stars, deep-sky objects, the Moon and planets.'))->noindex();
        $input = ['date' => '', 'timezone' => 'UTC', 'lat' => '', 'lon' => '', 'min_altitude_deg' => 20,
            'sun_altitude_deg' => -12, 'min_moon_separation_deg' => 0, 'window_start_utc' => '', 'window_end_utc' => '', 'horizon' => '',
            'equipment_mode' => 'naked_eye', 'preference' => 'balanced', 'true_field_deg' => '', 'max_catalogue_v_magnitude' => '', 'shortlist_limit' => 6];
        $result = null;
        $problem = null;
        $validation = [];
        $status = 200;
        if ($request->isMethod('POST')) {
            $posted = $request->attributes->get('shortlistInput', []);
            foreach ($input as $key => $default) {
                $value = $posted[$key] ?? null;
                if ((is_string($value) || is_int($value) || is_float($value)) && strlen((string) $value) <= ($key === 'horizon' ? 5000 : 100)) {
                    $input[$key] = (string) $value;
                }
            }
            try {
                $query = ShortlistRequest::parse($posted);
                $input = $query;
                $input['horizon'] = implode("\n", array_map(static fn (array $point): string => $point['azimuth_deg'].' '.$point['min_altitude_deg'], $query['horizon_mask'] ?? []));
                $input['window_start_utc'] ??= '';
                $input['window_end_utc'] ??= '';
                $input['true_field_deg'] ??= '';
                $input['max_catalogue_v_magnitude'] ??= '';
                $result = $planner->calculate($query);
            } catch (ValidationException $exception) {
                $validation = $exception->errors();
                $problem = __('Check the highlighted fields. No shortlist was requested.');
                $status = 422;
            } catch (SolarApiException) {
                $problem = __('Target shortlisting is unavailable for this date or service. No substitute candidates or positions are shown.');
                $status = 503;
            }
        }

        return response()->view('observing.shortlist', compact('input', 'result', 'problem', 'validation'), $status)
            ->header('Cache-Control', 'private, no-store')->header('Referrer-Policy', 'no-referrer');
    }
}
