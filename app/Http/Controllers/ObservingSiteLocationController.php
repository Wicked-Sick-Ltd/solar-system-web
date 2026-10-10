<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\What3Words\What3WordsClient;
use App\Services\What3Words\What3WordsException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use JsonException;

/** Resolve an observing-site location through what3words. The API key stays here. */
final class ObservingSiteLocationController
{
    public function locate(Request $request, What3WordsClient $client): JsonResponse
    {
        if ($request->query->count() !== 0) {
            return $this->privateJson(['message' => __('Send the what3words address in the request body.')], 422);
        }
        if (! $client->enabled()) {
            return $this->privateJson(['message' => __('what3words addresses aren\'t available here right now.')], 404);
        }
        if (! $request->isJson()) {
            return $this->privateJson(['message' => __('Send the what3words address as JSON.')], 415);
        }

        $payload = $this->payload($request);
        if ($payload === false) {
            return $this->privateJson(['message' => __('That request is too large.')], 413);
        }
        if ($payload === null) {
            return $this->privateJson(['message' => __('Send the what3words address as JSON.')], 422);
        }
        $words = $payload['words'] ?? null;
        if (! is_string($words)) {
            return $this->privateJson(['message' => __('Enter a what3words address as three words, such as ///filled.count.soap.')], 422);
        }

        try {
            $place = $client->locate($words);
        } catch (What3WordsException $e) {
            $status = match ($e->kind) {
                'invalid', 'unrecognised' => 422,
                'disabled' => 404,
                default => 503,
            };

            return $this->privateJson(['message' => $e->getMessage()], $status);
        }

        return $this->privateJson($place);
    }

    public function coordinates(Request $request, What3WordsClient $client): JsonResponse
    {
        if ($request->query->count() !== 0) {
            return $this->privateJson(['message' => __('Send coordinates in the request body.')], 422);
        }
        if (! $client->enabled()) {
            return $this->privateJson(['message' => __('what3words addresses aren\'t available here right now.')], 404);
        }
        if (! $request->isJson()) {
            return $this->privateJson(['message' => __('Send coordinates as JSON.')], 415);
        }

        $payload = $this->payload($request, 16384);
        if ($payload === false) {
            return $this->privateJson(['message' => __('That request is too large.')], 413);
        }
        if ($payload === null) {
            return $this->privateJson(['message' => __('Send coordinates as JSON.')], 422);
        }
        $rows = $payload['coordinates'] ?? null;
        if (! is_array($rows)) {
            return $this->privateJson(['message' => __('Enter coordinates to look up.')], 422);
        }
        if (count($rows) > 100) {
            return $this->privateJson(['message' => __('Look up at most 100 sites at a time.')], 422);
        }

        $budget = max(0, (int) config('services.what3words.reverse_budget', 8));
        $results = [];
        foreach ($rows as $row) {
            if (! is_array($row) || ! is_numeric($row['latitude'] ?? null) || ! is_numeric($row['longitude'] ?? null)) {
                continue;
            }
            $lat = (float) $row['latitude'];
            $lon = (float) $row['longitude'];
            if ($lat < -90 || $lat > 90 || $lon < -180 || $lon > 180) {
                continue;
            }

            $cached = $client->wordsFor($lat, $lon, false);
            if ($cached !== null) {
                $results[] = $cached;

                continue;
            }
            if ($budget <= 0) {
                continue;
            }
            $budget--;
            $live = $client->wordsFor($lat, $lon, true);
            if ($live !== null) {
                $results[] = $live;
            }
        }

        return $this->privateJson(['results' => $results]);
    }

    /**
     * @return array<string, mixed>|false|null false when the body is too large
     */
    private function payload(Request $request, int $maxBytes = 4096): array|false|null
    {
        $raw = $request->getContent();
        if (strlen($raw) > $maxBytes) {
            return false;
        }
        try {
            $payload = json_decode($raw, true, 8, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return null;
        }

        return is_array($payload) ? $payload : null;
    }

    /** @param  array<string, mixed>  $body */
    private function privateJson(array $body, int $status = 200): JsonResponse
    {
        return response()->json($body, $status);
    }
}
