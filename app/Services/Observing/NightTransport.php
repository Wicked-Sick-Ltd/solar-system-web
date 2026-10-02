<?php

declare(strict_types=1);

namespace App\Services\Observing;

use App\Services\SolarApi\Exceptions\SolarApiException;
use GuzzleHttp\Exception\TransferException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

final class NightTransport
{
    /** No persistent cache, logging of coordinates, retries or per-sample HTTP fan-out.
     * @param  array<string,mixed>  $query  Validated observing form output.
     */
    public function send(string $endpoint, array $query): mixed
    {
        if (! in_array($endpoint, ['night', 'discover'], true)) {
            throw new \InvalidArgumentException('Unsupported observing endpoint.');
        }
        try {
            $response = Http::acceptJson()->connectTimeout(3)
                ->timeout(max(40, min(60, (int) config('services.solar.planner_timeout', 40))))
                ->withOptions(['sink' => new BoundedResponseStream, 'allow_redirects' => false])
                ->post(config('services.solar.base_url').'/observing/'.$endpoint, $query);
        } catch (ConnectionException|TransferException) {
            throw new SolarApiException('Night planning is temporarily unavailable.');
        }
        if (! $response->successful() || strlen($response->body()) > BoundedResponseStream::MAX_BYTES) {
            throw new SolarApiException('Night planning is unavailable for this night or backend.');
        }

        return $response->json();
    }
}
