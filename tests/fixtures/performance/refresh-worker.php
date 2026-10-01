<?php

use App\Jobs\RefreshSolarCache;
use App\Services\SolarApi\SolarApiClient;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

// Independent Laravel processes share only this test's SQLite queue and file
// cache. Every HTTP response is synthetic; no environment credentials are used.
require $argv[1].'/vendor/autoload.php';
$app = require $argv[1].'/bootstrap/app.php';
$app->loadEnvironmentFrom('.env.performance-test-does-not-exist');
$app->make(Kernel::class)->bootstrap();
$directory = $argv[2];
$mode = $argv[3];
config([
    'cache.default' => 'file',
    'cache.stores.file.path' => $directory.'/cache',
    'cache.stores.file.lock_path' => $directory.'/cache',
    'database.connections.refresh_performance' => [
        'driver' => 'sqlite', 'database' => $directory.'/queue.sqlite', 'busy_timeout' => 5000,
    ],
    'queue.default' => 'refresh_performance',
    'queue.connections.refresh_performance' => [
        'driver' => 'database', 'connection' => 'refresh_performance', 'table' => 'jobs',
        'queue' => 'default', 'retry_after' => 90, 'after_commit' => false,
    ],
    'queue.failed.driver' => 'null',
    'services.solar.base_url' => 'https://catalogue.example.test/api/v1',
    'logging.default' => 'null',
]);
$key = 'solar:'.sha1('/stats?');
Http::preventStrayRequests();
Http::fake(function () use ($mode, $directory, $key) {
    file_put_contents($directory.'/http-calls', "request\n", FILE_APPEND | LOCK_EX);
    // Re-dispatch while the refresh is actively handling its HTTP request.
    // ShouldBeUniqueUntilProcessing would incorrectly allow this second job.
    RefreshSolarCache::dispatch('/stats', [], $key, 60);
    file_put_contents($directory.'/during-worker-count', (string) DB::connection('refresh_performance')->table('jobs')->count());
    if ($mode === 'fail') {
        throw new ConnectionException('Synthetic offline failure');
    }

    return Http::response(['total_objects' => 84]);
});
if ($mode === 'read') {
    $values = [];
    for ($i = 0; $i < 25; $i++) {
        $values[] = app(SolarApiClient::class)->stats()->totalObjects;
    }
    echo json_encode($values);
} else {
    Artisan::call('queue:work', [
        'connection' => 'refresh_performance', '--once' => true, '--sleep' => 0, '--quiet' => true,
    ]);
}
