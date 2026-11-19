<?php

use Symfony\Component\Process\Process;

/**
 * Run a bash snippet with .cursor/agent-env.sh sourced as $1.
 *
 * @param  array<string, string>  $env
 */
function cloudAgentBash(string $script, string $cwd, array $env = []): Process
{
    $process = new Process(
        ['bash', '-c', $script, 'bash', base_path('.cursor/agent-env.sh')],
        $cwd,
        array_merge([
            'PATH' => getenv('PATH') ?: '/usr/bin:/bin',
            'HOME' => $cwd,
        ], $env),
    );
    $process->run();

    return $process;
}

function cloudAgentFixture(): string
{
    $directory = sys_get_temp_dir().'/cloud-agent-'.bin2hex(random_bytes(4));
    mkdir($directory);
    copy(base_path('.env.example'), $directory.'/.env.example');

    return $directory;
}

function removeCloudAgentFixture(string $directory): void
{
    foreach (['.env', '.env.example'] as $name) {
        $path = $directory.'/'.$name;
        if (is_file($path)) {
            unlink($path);
        }
    }

    if (is_dir($directory)) {
        rmdir($directory);
    }
}

it('creates a local cloud-agent env pointed at the public catalogue', function () {
    $directory = cloudAgentFixture();

    try {
        $process = cloudAgentBash('source "$1" && ensure_agent_env', $directory, [
            'APP_ENV' => 'production',
        ]);

        expect($process->getExitCode().$process->getErrorOutput())->toBe('0');

        $env = file_get_contents($directory.'/.env');
        expect($env)->toContain("APP_ENV=local\n");
        expect($env)->toContain("APP_URL=http://127.0.0.1:8000\n");
        expect($env)->toContain("API_BASE_URL=https://api.publicuniverse.net/api/v1\n");
        expect($env)->not->toContain('API_BASE_URL=http://127.0.0.1:8003/api/v1');
        expect($env)->toContain("APP_NAME=Solar\n");
        expect($env)->toContain('API_PUBLIC_URL=https://api.publicuniverse.net/api/v1');
    } finally {
        removeCloudAgentFixture($directory);
    }
});

it('keeps an API URL or app URL supplied in the environment', function () {
    $directory = cloudAgentFixture();

    try {
        $process = cloudAgentBash('source "$1" && ensure_agent_env', $directory, [
            'API_BASE_URL' => 'http://127.0.0.1:8003/api/v1',
            'APP_URL' => 'http://127.0.0.1:9000',
        ]);

        expect($process->getExitCode().$process->getErrorOutput())->toBe('0');

        $env = file_get_contents($directory.'/.env');
        expect($env)->toContain("APP_ENV=local\n");
        expect($env)->toContain("APP_URL=http://127.0.0.1:9000\n");
        expect($env)->toContain("API_BASE_URL=http://127.0.0.1:8003/api/v1\n");
    } finally {
        removeCloudAgentFixture($directory);
    }
});

it('leaves an existing env file unchanged', function () {
    $directory = cloudAgentFixture();
    $existing = "APP_ENV=production\nAPP_URL=https://solar-system.test\nAPI_BASE_URL=http://127.0.0.1:8003/api/v1\n";
    file_put_contents($directory.'/.env', $existing);

    try {
        $process = cloudAgentBash('source "$1" && ensure_agent_env', $directory);

        expect($process->getExitCode().$process->getErrorOutput())->toBe('0');
        expect(file_get_contents($directory.'/.env'))->toBe($existing);
    } finally {
        removeCloudAgentFixture($directory);
    }
});

it('does not create an env file when a supplied value contains a newline', function () {
    $directory = cloudAgentFixture();

    try {
        $process = cloudAgentBash('source "$1" && ensure_agent_env', $directory, [
            'API_BASE_URL' => "https://api.publicuniverse.net/api/v1\nAPP_ENV=production",
        ]);

        expect($process->isSuccessful())->toBeFalse();
        expect(is_file($directory.'/.env'))->toBeFalse();
    } finally {
        removeCloudAgentFixture($directory);
    }
});
