<?php

declare(strict_types=1);

/**
 * The measurement id is resolved when config/site.php is loaded. Prefer the
 * GA4_MEASUREMENT_ID name; keep GA_MEASUREMENT_ID as a one-release fallback.
 */
it('prefers GA4_MEASUREMENT_ID over the legacy GA_MEASUREMENT_ID name', function () {
    $previousGa4 = $_ENV['GA4_MEASUREMENT_ID'] ?? false;
    $previousGa = $_ENV['GA_MEASUREMENT_ID'] ?? false;

    try {
        $_ENV['GA4_MEASUREMENT_ID'] = 'G-PREFERRED';
        $_ENV['GA_MEASUREMENT_ID'] = 'G-LEGACY';
        putenv('GA4_MEASUREMENT_ID=G-PREFERRED');
        putenv('GA_MEASUREMENT_ID=G-LEGACY');

        $config = require __DIR__.'/../../config/site.php';

        expect($config['analytics']['ga_measurement_id'])->toBe('G-PREFERRED');
    } finally {
        restoreEnv('GA4_MEASUREMENT_ID', $previousGa4);
        restoreEnv('GA_MEASUREMENT_ID', $previousGa);
    }
});

it('falls back to GA_MEASUREMENT_ID when GA4_MEASUREMENT_ID is unset', function () {
    $previousGa4 = $_ENV['GA4_MEASUREMENT_ID'] ?? false;
    $previousGa = $_ENV['GA_MEASUREMENT_ID'] ?? false;

    try {
        unset($_ENV['GA4_MEASUREMENT_ID'], $_SERVER['GA4_MEASUREMENT_ID']);
        putenv('GA4_MEASUREMENT_ID');
        $_ENV['GA_MEASUREMENT_ID'] = 'G-LEGACY';
        putenv('GA_MEASUREMENT_ID=G-LEGACY');

        $config = require __DIR__.'/../../config/site.php';

        expect($config['analytics']['ga_measurement_id'])->toBe('G-LEGACY');
    } finally {
        restoreEnv('GA4_MEASUREMENT_ID', $previousGa4);
        restoreEnv('GA_MEASUREMENT_ID', $previousGa);
    }
});

it('leaves analytics unset when neither measurement id is present', function () {
    $previousGa4 = $_ENV['GA4_MEASUREMENT_ID'] ?? false;
    $previousGa = $_ENV['GA_MEASUREMENT_ID'] ?? false;

    try {
        unset($_ENV['GA4_MEASUREMENT_ID'], $_SERVER['GA4_MEASUREMENT_ID'], $_ENV['GA_MEASUREMENT_ID'], $_SERVER['GA_MEASUREMENT_ID']);
        putenv('GA4_MEASUREMENT_ID');
        putenv('GA_MEASUREMENT_ID');

        $config = require __DIR__.'/../../config/site.php';

        expect($config['analytics']['ga_measurement_id'])->toBeNull();
    } finally {
        restoreEnv('GA4_MEASUREMENT_ID', $previousGa4);
        restoreEnv('GA_MEASUREMENT_ID', $previousGa);
    }
});

function restoreEnv(string $key, mixed $previous): void
{
    if ($previous === false) {
        unset($_ENV[$key], $_SERVER[$key]);
        putenv($key);

        return;
    }

    $_ENV[$key] = $previous;
    putenv($key.'='.$previous);
}
