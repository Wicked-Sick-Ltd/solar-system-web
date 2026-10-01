<?php

declare(strict_types=1);

use App\Jobs\RefreshSolarCache;
use App\Services\SolarApi\SolarApiClient;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\Process\Process;

beforeEach(function () {
    $this->directory = sys_get_temp_dir().'/universe-refresh-'.bin2hex(random_bytes(8));
    File::makeDirectory($this->directory.'/cache', 0755, true);
    touch($this->directory.'/queue.sqlite');
    config([
        'cache.default' => 'file',
        'cache.stores.file.path' => $this->directory.'/cache',
        'cache.stores.file.lock_path' => $this->directory.'/cache',
        'database.connections.refresh_performance' => [
            'driver' => 'sqlite', 'database' => $this->directory.'/queue.sqlite', 'busy_timeout' => 5000,
        ],
        'queue.default' => 'refresh_performance',
        'queue.connections.refresh_performance' => [
            'driver' => 'database', 'connection' => 'refresh_performance', 'table' => 'jobs',
            'queue' => 'default', 'retry_after' => 90, 'after_commit' => false,
        ],
        'services.solar.base_url' => 'https://catalogue.example.test/api/v1',
    ]);
    Schema::connection('refresh_performance')->create('jobs', function (Blueprint $table) {
        $table->bigIncrements('id');
        $table->string('queue')->index();
        $table->longText('payload');
        $table->unsignedTinyInteger('attempts');
        $table->unsignedInteger('reserved_at')->nullable();
        $table->unsignedInteger('available_at');
        $table->unsignedInteger('created_at');
    });
    $this->key = 'solar:'.sha1('/stats?');
    $this->stale = ['value' => ['total_objects' => 42], 'soft' => time() - 1];
    Cache::put($this->key, $this->stale, 3600);
    Http::preventStrayRequests();
});

afterEach(function () {
    DB::purge('refresh_performance');
    File::deleteDirectory($this->directory);
});

function refreshProcess(string $directory, string $mode): Process
{
    return new Process([PHP_BINARY, base_path('tests/fixtures/performance/refresh-worker.php'), base_path(), $directory, $mode], env: ['APP_ENV' => 'testing']);
}

it('queues one refresh for 100 stale reads across independent producers and keeps the lock through execution', function () {
    $processes = [];
    try {
        foreach (range(1, 4) as $i) {
            $process = refreshProcess($this->directory, 'read');
            $process->start();
            $processes[] = $process;
        }
        foreach ($processes as $process) {
            $process->wait();
            expect($process->isSuccessful())->toBeTrue($process->getErrorOutput())
                ->and(json_decode($process->getOutput(), true))->toBe(array_fill(0, 25, 42));
        }
    } finally {
        foreach ($processes as $process) {
            if ($process->isRunning()) {
                $process->stop();
            }
        }
    }
    expect(DB::connection('refresh_performance')->table('jobs')->count())->toBe(1)
        ->and(Cache::get($this->key))->toBe($this->stale)
        ->and(file_exists($this->directory.'/http-calls'))->toBeFalse();
    $worker = refreshProcess($this->directory, 'success');
    $worker->mustRun();
    expect(file_get_contents($this->directory.'/http-calls'))->toBe("request\n")
        ->and(file_get_contents($this->directory.'/during-worker-count'))->toBe('1')
        ->and(DB::connection('refresh_performance')->table('jobs')->count())->toBe(0)
        ->and(Cache::get($this->key)['value']['total_objects'])->toBe(84);
    Cache::put($this->key, $this->stale, 3600);
    app(SolarApiClient::class)->stats();
    expect(DB::connection('refresh_performance')->table('jobs')->count())->toBe(1);
    Http::assertNothingSent();
});

it('keeps stale data after a failed worker refresh and releases uniqueness for the next read', function () {
    app(SolarApiClient::class)->stats();
    refreshProcess($this->directory, 'fail')->mustRun();
    expect(Cache::get($this->key))->toBe($this->stale)
        ->and(DB::connection('refresh_performance')->table('jobs')->count())->toBe(0)
        ->and(file_get_contents($this->directory.'/during-worker-count'))->toBe('1');
    app(SolarApiClient::class)->stats();
    expect(DB::connection('refresh_performance')->table('jobs')->count())->toBe(1);
    refreshProcess($this->directory, 'success')->mustRun();
    expect(Cache::get($this->key)['value']['total_objects'])->toBe(84);
});

it('deduplicates by the full cache key while allowing different resources or filters to refresh', function () {
    foreach ([$this->key, 'solar:filter-one', 'solar:filter-two'] as $key) {
        foreach (range(1, 3) as $i) {
            RefreshSolarCache::dispatch('/stats', [], $key, 60);
        }
    }
    expect(DB::connection('refresh_performance')->table('jobs')->count())->toBe(3);
});

it('bounds abandoned unique locks while reserving time for a complete worker attempt', function () {
    $job = new RefreshSolarCache('/stats', [], $this->key, 60);
    expect($job->uniqueFor)->toBe(900)->and($job->timeout)->toBe(60)
        ->and($job->uniqueFor)->toBeGreaterThan($job->timeout)
        ->and($job->timeout)->toBeLessThan(config('queue.connections.refresh_performance.retry_after'));
    app(SolarApiClient::class)->stats();
    $this->travel($job->uniqueFor - 1)->seconds();
    app(SolarApiClient::class)->stats();
    expect(DB::connection('refresh_performance')->table('jobs')->count())->toBe(1);
    $this->travel(2)->seconds();
    app(SolarApiClient::class)->stats();
    expect(DB::connection('refresh_performance')->table('jobs')->count())->toBe(2);
    // The original queued job may finish after its lease expired. Its old
    // owner must not release the replacement producer's lock.
    refreshProcess($this->directory, 'success')->mustRun();
    Cache::put($this->key, $this->stale, 3600);
    app(SolarApiClient::class)->stats();
    expect(DB::connection('refresh_performance')->table('jobs')->count())->toBe(1);
});

it('preserves synchronous refresh behavior and releases its unique lock after failure', function () {
    config(['queue.default' => 'sync']);
    Http::fakeSequence()->pushStatus(503)->push(['total_objects' => 84]);
    expect(app(SolarApiClient::class)->stats()->totalObjects)->toBe(42)
        ->and(Cache::get($this->key))->toBe($this->stale);
    expect(app(SolarApiClient::class)->stats()->totalObjects)->toBe(42)
        ->and(Cache::get($this->key)['value']['total_objects'])->toBe(84);
    expect(app(SolarApiClient::class)->stats()->totalObjects)->toBe(84);
    Http::assertSentCount(2);
});
