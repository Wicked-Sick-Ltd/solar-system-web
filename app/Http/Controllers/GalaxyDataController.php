<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\SolarApi\Exceptions\SolarApiException;
use App\Services\SolarApi\SolarApiClient;
use Illuminate\Http\JsonResponse;

final class GalaxyDataController
{
    public function __invoke(SolarApiClient $api): JsonResponse
    {
        try {
            $map = $api->galaxyMap();

            return response()->json(['hosts' => $map->hosts]);
        } catch (SolarApiException) {
            return response()->json(['error' => 'Map temporarily unavailable'], 503);
        }
    }
}
