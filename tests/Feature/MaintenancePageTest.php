<?php

declare(strict_types=1);

use Symfony\Component\Process\Process;

it('serves prerendered maintenance HTML without bootstrapping the application', function () {
    $directory = sys_get_temp_dir().'/solar-maintenance-'.bin2hex(random_bytes(8));
    mkdir($directory, 0700);
    $html = view('errors.503')->render();

    try {
        file_put_contents($directory.'/down', json_encode([
            'template' => $html,
            'status' => 503,
            'retry' => 60,
        ], JSON_THROW_ON_ERROR));
        copy(base_path('vendor/laravel/framework/src/Illuminate/Foundation/Console/stubs/maintenance-mode.stub'), $directory.'/maintenance.php');
        file_put_contents($directory.'/index.php', <<<'SCRIPT'
<?php
$_SERVER['REQUEST_URI'] = '/';
$_SERVER['HTTP_ACCEPT'] = 'text/html';
register_shutdown_function(static function (): void { fwrite(STDERR, 'status='.http_response_code()); });
require __DIR__.'/maintenance.php';
throw new RuntimeException('Application bootstrap must not run');
SCRIPT);
        // This subprocess has no Composer autoloader or application files.
        $process = new Process([PHP_BINARY, $directory.'/index.php']);
        $process->mustRun();

        expect($process->getErrorOutput())->toBe('status=503');
        expect($process->getOutput())->toBe($html)
            ->toContain('Briefly down for maintenance')
            ->not->toContain('<script', '<link', 'wire:');
    } finally {
        foreach (['down', 'maintenance.php', 'index.php'] as $file) {
            if (file_exists($directory.'/'.$file)) {
                unlink($directory.'/'.$file);
            }
        }
        rmdir($directory);
    }
});
