<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\Exports\ExoplanetPageExport;
use App\Services\SolarApi\Data\ExoplanetFilters;
use App\Services\SolarApi\Exceptions\SolarApiException;
use App\Services\SolarApi\SolarApiClient;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

final class ExoplanetExportController
{
    public function __invoke(Request $request, string $format, SolarApiClient $api, ExoplanetPageExport $export): Response
    {
        $headers = ['Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff'];
        try {
            $filters = ExoplanetFilters::fromInput($request->query());
        } catch (ValidationException $exception) {
            return response()->json(['error' => 'Invalid export filters.', 'errors' => $exception->errors()], 422, $headers);
        }
        try {
            $page = $api->exoplanets($filters->apiFilters(), ExoplanetFilters::PER_PAGE, $filters->offset());
            $document = $export->document($page, $filters);
            $body = $format === 'csv' ? $export->csv($document) : $export->json($document);
        } catch (SolarApiException) {
            return response()->json(['error' => 'Exoplanet export temporarily unavailable.'], 503, $headers);
        }

        return response($body, 200, $headers + [
            'Content-Type' => $format === 'csv' ? 'text/csv; charset=UTF-8' : 'application/json; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="exoplanets-page-'.$filters->page.'.'.$format.'"',
        ]);
    }
}
