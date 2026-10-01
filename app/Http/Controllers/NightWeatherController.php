<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\Weather\NightWeatherRequest;
use App\Services\Weather\NightWeatherService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

final class NightWeatherController
{
    public function __invoke(Request $request, NightWeatherService $service): Response
    {
        $forecast = null;
        $problem = null;
        $status = 200;
        try {
            $input = $request->attributes->get('nightWeatherInput', []);
            $forecast = $service->forecast(NightWeatherRequest::parse($input));
            $status = $forecast['status'] === 'unavailable' ? 503 : 200;
        } catch (ValidationException) {
            // Do not flash location or arbitrary input into the session.
            $status = 422;
            $problem = 'Supply valid coordinates and a positive UTC interval of at most 26 hours.';
        }

        return $request->expectsJson()
            ? response()->json($forecast ?? ['status' => 'invalid_request', 'message' => $problem], $status)
            : response()->view('observing.night-weather', compact('forecast', 'problem'), $status);
    }
}
