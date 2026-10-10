<?php

declare(strict_types=1);

namespace App\Services;

use App\Support\Flyby\VectorSample;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Geocentric ecliptic state vectors from the public JPL Horizons API.
 * Successful reads are cached; a miss returns null so the caller can fall
 * back to a labelled approximation.
 */
class HorizonsClient
{
    /**
     * @return list<VectorSample>|null
     */
    public function arc(string $command, CarbonImmutable $start, CarbonImmutable $stop): ?array
    {
        $samples = $this->vectors($command, $start, $stop, '3 h');

        return $samples !== null && count($samples) >= 2 ? $samples : null;
    }

    public function instant(string $command, CarbonImmutable $at): ?VectorSample
    {
        $samples = $this->vectors($command, $at->utc(), $at->utc()->addMinute(), '1 m') ?? [];

        return $samples[0] ?? null;
    }

    /**
     * @return list<VectorSample>|null
     */
    public function vectors(string $command, CarbonImmutable $start, CarbonImmutable $stop, string $step): ?array
    {
        $command = $this->command($command);
        if ($command === null) {
            return null;
        }

        $startText = $start->utc()->format('Y-m-d H:i');
        $stopText = $stop->utc()->format('Y-m-d H:i');
        $key = 'horizons:v1:'.sha1($command.'|'.$startText.'|'.$stopText.'|'.$step);
        $cached = Cache::get($key);
        if ($cached === 'miss') {
            return null;
        }
        if (is_array($cached)) {
            $samples = $this->hydrate($cached);

            return $samples === [] ? null : $samples;
        }

        $samples = $this->fetch($command, $startText, $stopText, $step);
        if ($samples === null) {
            // A short miss cache keeps a dead ephemeris from delaying every page view.
            Cache::put($key, 'miss', now()->addMinutes(10));

            return null;
        }
        Cache::put($key, array_map(fn (VectorSample $sample): array => [
            'jd' => $sample->jd,
            'x' => $sample->xKm,
            'y' => $sample->yKm,
            'z' => $sample->zKm,
            'vx' => $sample->vxKmS,
            'vy' => $sample->vyKmS,
            'vz' => $sample->vzKmS,
        ], $samples), now()->addSeconds((int) config('services.horizons.cache_seconds', 43200)));

        return $samples;
    }

    /**
     * @return list<VectorSample>|null
     */
    public static function parse(string $result): ?array
    {
        $start = strpos($result, '$$SOE');
        $end = strpos($result, '$$EOE');
        if ($start === false || $end === false || $end <= $start) {
            return null;
        }

        $samples = [];
        foreach (preg_split("/\r\n|\n|\r/", substr($result, $start + 5, $end - $start - 5)) ?: [] as $line) {
            if (trim($line) === '') {
                continue;
            }
            $fields = array_map(trim(...), explode(',', $line));
            if (count($fields) < 8 || ! is_numeric($fields[0]) || ! is_numeric($fields[2]) || ! is_numeric($fields[3]) || ! is_numeric($fields[4])) {
                continue;
            }
            $sample = new VectorSample(
                (float) $fields[0],
                (float) $fields[2],
                (float) $fields[3],
                (float) $fields[4],
                is_numeric($fields[5]) ? (float) $fields[5] : null,
                is_numeric($fields[6]) ? (float) $fields[6] : null,
                is_numeric($fields[7]) ? (float) $fields[7] : null,
            );
            if ($sample->finite()) {
                $samples[] = $sample;
            }
        }

        return $samples === [] ? null : $samples;
    }

    /**
     * @return list<VectorSample>|null
     */
    private function fetch(string $command, string $start, string $stop, string $step): ?array
    {
        try {
            $response = Http::timeout((int) config('services.horizons.timeout', 6))
                ->connectTimeout(3)
                ->acceptJson()
                ->get((string) config('services.horizons.base_url'), [
                    'format' => 'json',
                    'COMMAND' => "'".$command."'",
                    'EPHEM_TYPE' => 'VECTORS',
                    'CENTER' => "'500@399'",
                    'START_TIME' => "'".$start."'",
                    'STOP_TIME' => "'".$stop."'",
                    'STEP_SIZE' => "'".$step."'",
                    'OUT_UNITS' => 'KM-S',
                    'REF_PLANE' => 'ECLIPTIC',
                    'REF_SYSTEM' => 'J2000',
                    'VEC_TABLE' => '2',
                    'CSV_FORMAT' => 'YES',
                ]);
        } catch (Throwable $e) {
            Log::notice('Horizons ephemeris request failed', ['error' => $e->getMessage()]);

            return null;
        }

        $result = $response->successful() ? $response->json('result') : null;

        return is_string($result) ? self::parse($result) : null;
    }

    /**
     * @param  list<mixed>  $rows
     * @return list<VectorSample>
     */
    private function hydrate(array $rows): array
    {
        $samples = [];
        foreach ($rows as $row) {
            if (! is_array($row) || ! is_numeric($row['jd'] ?? null) || ! is_numeric($row['x'] ?? null) || ! is_numeric($row['y'] ?? null) || ! is_numeric($row['z'] ?? null)) {
                continue;
            }
            $samples[] = new VectorSample(
                (float) $row['jd'],
                (float) $row['x'],
                (float) $row['y'],
                (float) $row['z'],
                is_numeric($row['vx'] ?? null) ? (float) $row['vx'] : null,
                is_numeric($row['vy'] ?? null) ? (float) $row['vy'] : null,
                is_numeric($row['vz'] ?? null) ? (float) $row['vz'] : null,
            );
        }

        return $samples;
    }

    private function command(string $command): ?string
    {
        $command = trim($command);
        if ($command === '' || strlen($command) > 40 || ! preg_match('/^[A-Za-z0-9][A-Za-z0-9 .\/+_()=;-]*$/', $command)) {
            return null;
        }

        return $command;
    }
}
