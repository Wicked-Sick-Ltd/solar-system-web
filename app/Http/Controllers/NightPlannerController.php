<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\Observing\NightPlanner;
use App\Services\Observing\NightRequest;
use App\Services\Observing\NightSession;
use App\Services\Observing\NightTargets;
use App\Services\SolarApi\Exceptions\SolarApiException;
use App\Support\Seo;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;

final class NightPlannerController
{
    public function __invoke(Request $request, NightPlanner $planner): Response
    {
        app(Seo::class)->title(__('Plan a night'))->description(__('Choose a night and location for explained Moon, planet and catalogue observing windows.'))->noindex();
        $input = ['date' => '', 'timezone' => 'UTC', 'lat' => '', 'lon' => '', 'targets' => ['moon', 'jupiter', 'saturn'],
            'min_altitude_deg' => 20, 'sun_altitude_deg' => -12, 'min_moon_separation_deg' => 0,
            'window_start_utc' => '', 'window_end_utc' => '', 'horizon' => '', 'catalogue_targets' => ''];
        $plan = null;
        $sessionSummary = null;
        $problem = null;
        $validation = [];
        $status = 200;
        if ($request->isMethod('GET')) {
            try {
                $hints = NightTargets::hints($request->query());
                if ($hints !== []) {
                    $input['targets'] = $hints;
                    $input['catalogue_targets'] = implode(', ', array_filter($hints, NightTargets::isCatalogueId(...)));
                }
            } catch (ValidationException $exception) {
                $validation = $exception->errors();
                $problem = __('The target link is invalid. Choose targets below; no calculation was requested.');
                $status = 422;
            }
        }
        if ($request->isMethod('POST')) {
            // Bounded display values only; calculation still requires full validation.
            $posted = $request->request->all();
            foreach (array_keys($input) as $key) {
                $value = $posted[$key] ?? null;
                if ($key === 'targets') {
                    $input[$key] = is_array($value) ? array_values(array_filter(array_slice($value, 0, 8),
                        static fn ($target): bool => NightTargets::isSupportedId($target))) : [];
                } elseif ((is_string($value) || is_int($value) || is_float($value)) && strlen((string) $value) <= ($key === 'horizon' ? 5000 : ($key === 'catalogue_targets' ? 400 : 100))) {
                    $input[$key] = (string) $value;
                }
            }
            try {
                $query = NightRequest::parse($request->post());
                $input = $query;
                $input['targets'] = explode(',', $query['targets']);
                $input['catalogue_targets'] = implode(', ', array_filter($input['targets'], NightTargets::isCatalogueId(...)));
                $input['horizon'] = implode("\n", array_map(static fn (array $point): string => $point['azimuth_deg'].' '.$point['min_altitude_deg'], $query['horizon_mask'] ?? []));
                $plan = $planner->calculate($query);
                $sessionSummary = NightSession::summary($plan, $query);
            } catch (ValidationException $exception) {
                // Render here: never flash private coordinates to a session or URL.
                $validation = $exception->errors();
                $problem = __('Check the highlighted fields. No calculation was requested.');
                $status = 422;
            } catch (SolarApiException) {
                $plan = null;
                $sessionSummary = null;
                $problem = __('Night planning is unavailable for this date or service. The offline calculation has limited date coverage; no substitute positions are shown.');
                $status = 503;
            }
        }

        return response()->view('observing.night', compact('input', 'plan', 'problem', 'validation', 'sessionSummary'), $status)
            ->header('Cache-Control', 'private, no-store')
            ->header('Referrer-Policy', 'no-referrer');
    }
}
