<?php

declare(strict_types=1);

use App\Services\Weather\BoundedWeatherStream;
use App\Services\Weather\Data\HourlyForecast;
use App\Services\Weather\OpenMeteoClient;
use GuzzleHttp\Client;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Symfony\Component\Process\Process;

it('stops actual plain and gzip HTTP bodies at the decoded stream bound', function (string $path) {
    $server = new Process(['python3', base_path('tests/fixtures/weather-http-server.py')]);
    $server->start();
    try {
        expect($server->waitUntil(fn ($type, $output) => preg_match('/^[0-9]+\n$/D', $output) === 1))->toBeTrue();
        $port = trim($server->getOutput());
        $sink = new BoundedWeatherStream;
        $client = new Client(['timeout' => 5, 'proxy' => '', 'allow_redirects' => false]);
        try {
            $client->get('http://127.0.0.1:'.$port.'/'.$path.'/v1/forecast', ['sink' => $sink]);
            $this->fail('An oversized decoded body must fail before JSON parsing.');
        } catch (RuntimeException $error) {
            expect($error->getMessage())->toContain('Forecast response exceeded its size limit.');
        }
        expect($sink->getSize())->toBeLessThanOrEqual(BoundedWeatherStream::MAX_BYTES);
    } finally {
        $server->stop();
    }
})->with(['plain', 'gzip']);

it('disables forecast redirects and bounds fake bodies too', function () {
    config(['cache.default' => 'array', 'services.open_meteo.base_url' => 'https://weather.example.test']);
    Cache::flush();
    Http::swap(new Factory);
    Http::fake(['*' => function ($request, $options) {
        expect($options['allow_redirects'])->toBeFalse()->and($options['sink'])->toBeInstanceOf(BoundedWeatherStream::class);

        return Http::response(str_repeat(' ', BoundedWeatherStream::MAX_BYTES + 1));
    }]);
    expect(app(OpenMeteoClient::class)->hourlyForecast(0, 0))->toBeNull();
});

it('roundtrips a scalar forecast through the actual secure file cache', function () {
    $directory = sys_get_temp_dir().'/weather-cache-'.bin2hex(random_bytes(8));
    mkdir($directory, 0700);
    config(['cache.default' => 'file', 'cache.stores.file.path' => $directory, 'cache.stores.file.lock_path' => $directory,
        'services.open_meteo.base_url' => 'https://weather.example.test']);
    Cache::forgetDriver('file');
    Http::swap(new Factory);
    Http::preventStrayRequests();
    Http::fake(['*' => Http::response(['hourly' => ['time' => ['2026-10-01T21:00'], 'cloud_cover' => [0]]])]);
    try {
        $one = app(OpenMeteoClient::class)->hourlyForecast(0, 0);
        Cache::forgetDriver('file');
        $two = app(OpenMeteoClient::class)->hourlyForecast(0, 0);
        expect($one)->toBeInstanceOf(HourlyForecast::class)->and($two)->toEqual($one);
        Http::assertSentCount(1);
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($files as $file) {
            if ($file->isFile()) {
                expect(file_get_contents($file->getPathname()))->not->toContain('HourlyForecast');
            }
        }
    } finally {
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($files as $file) {
            $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
        }
        rmdir($directory);
        Cache::forgetDriver('file');
    }
});

it('rejects malformed scalar cache snapshots instead of treating them as validated forecasts', function (string $path, mixed $value) {
    $data = ['fetched_at_utc' => '2026-10-01T21:12:00Z', 'hours' => ['2026-10-01T21:00:00Z' => [
        'cloud_cover' => 0.0, 'relative_humidity_2m' => null, 'wind_speed_10m' => 1.25, 'visibility' => 1000.0,
    ]]];
    data_set($data, $path, $value);
    expect(HourlyForecast::fromCache($data))->toBeNull();
})->with([
    ['fetched_at_utc', 'tomorrow'], ['fetched_at_utc', '2026-02-30T21:00:00Z'], ['hours', []],
    ['hours.2026-10-01T21:00:00Z.cloud_cover', true], ['hours.2026-10-01T21:00:00Z.cloud_cover', 101],
    ['hours.2026-10-01T21:00:00Z.visibility', 1e30], ['hours.2026-10-01T21:00:00Z.wind_speed_10m', '1.25'],
    ['hours.2026-10-01T21:00:00Z.unexpected', 1],
]);
