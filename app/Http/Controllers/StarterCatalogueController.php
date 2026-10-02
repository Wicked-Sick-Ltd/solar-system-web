<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\SolarApi\Data\StarterFilters;
use App\Services\SolarApi\Exceptions\SolarApiException;
use App\Services\SolarApi\StarterCatalogueClient;
use App\Support\Seo;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;

final class StarterCatalogueController
{
    public function index(Request $request, StarterCatalogueClient $api): Response
    {
        app(Seo::class)->title(__('Observing target starter catalogues'))
            ->description(__('Browse sourced bright stars, double-star records and deep-sky objects, with historical catalogue measurements and explicit data licences.'));
        $input = $request->query();
        $values = [];
        foreach (['q', 'family'] as $field) {
            $values[$field] = is_string($input[$field] ?? null) ? $input[$field] : '';
        }
        $filters = null;
        $catalogue = null;
        $errors = [];
        $apiDown = false;
        $pages = 1;
        $status = 200;
        try {
            $filters = StarterFilters::fromInput($input);
            $catalogue = $api->catalogue($filters->q, $filters->family, $filters->page);
            $pages = max(1, (int) ceil($catalogue->total / StarterCatalogueClient::PER_PAGE));
            if ($filters->page > $pages) {
                throw ValidationException::withMessages(['page' => __('This page is outside the current results. Return to the first page with these filters.')]);
            }
        } catch (ValidationException $exception) {
            $errors = $exception->validator->errors()->all();
            $status = 422;
        } catch (SolarApiException) {
            $apiDown = true;
            $status = 503;
        }

        return response()->view('starter-targets.index', compact('values', 'filters', 'catalogue', 'pages', 'errors', 'apiDown'), $status);
    }

    public function show(string $id, StarterCatalogueClient $api): Response
    {
        if (! preg_match('/^(bsc5p:hr[1-9][0-9]*|openngc:[A-Za-z0-9-]+)$/D', $id)) {
            abort(404);
        }
        $target = null;
        $apiDown = false;
        $status = 200;
        try {
            $target = $api->target($id);
            $status = $target === null ? 404 : 200;
        } catch (SolarApiException) {
            $apiDown = true;
            $status = 503;
        }
        app(Seo::class)->title($target->name ?? __('Observing target'));

        return response()->view('starter-targets.show', compact('target', 'apiDown'), $status);
    }
}
