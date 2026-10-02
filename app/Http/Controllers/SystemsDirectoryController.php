<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\SolarApi\Data\HostDirectoryFilters;
use App\Services\SolarApi\Exceptions\SolarApiException;
use App\Services\SolarApi\SolarApiClient;
use App\Support\Seo;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;

final class SystemsDirectoryController
{
    public function __invoke(Request $request, SolarApiClient $api): Response
    {
        app(Seo::class)->title(__('Measured exoplanet systems'))
            ->description(__('Browse the measured exoplanet host systems returned by the galaxy map, with accessible name, distance and sorting controls.'));
        $input = $request->query();
        $values = [];
        foreach (['q' => '', 'radius' => 'all', 'order' => 'distance'] as $field => $default) {
            $values[$field] = is_string($input[$field] ?? null) ? trim($input[$field]) : $default;
        }
        $filters = null;
        $map = null;
        $selection = null;
        $errors = [];
        $apiDown = false;
        $status = 200;
        try {
            $filters = HostDirectoryFilters::fromInput($input);
            $map = $api->galaxyMap();
            $selection = $filters->select($map);
        } catch (ValidationException $exception) {
            $errors = $exception->validator->errors()->all();
            $status = 422;
        } catch (SolarApiException) {
            $apiDown = true;
            $status = 503;
        }

        return response()->view('systems.index', compact('values', 'filters', 'map', 'selection', 'errors', 'apiDown'), $status);
    }
}
