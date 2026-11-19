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
            // Pest boots Laravel, which loads .env into this process. Symfony
            // Process inherits those variables unless the same keys are set
            // here, so clear the ones the script treats as explicit overrides.
            'APP_ENV' => '',
            'APP_URL' => '',
            'API_BASE_URL' => '',
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

it('deletes a download whose digest does not match the pin', function () {
    $directory = cloudAgentFixture();
    $download = $directory.'/installer.php';
    file_put_contents($download, "<?php echo \"substituted\";\n");

    try {
        $process = new Process(
            [
                'bash', '-c', 'source "$1" && verify_digest sha384 "$2" "$3"',
                'bash',
                base_path('.cursor/agent-env.sh'),
                $download,
                'c8b085408188070d5f52bcfe4ecfbee5f727afa458b2573b8eaaf77b3419b0bf2768dc67c86944da1544f06fa544fd47',
            ],
            $directory,
            ['PATH' => getenv('PATH') ?: '/usr/bin:/bin', 'HOME' => $directory],
        );
        $process->run();

        expect($process->isSuccessful())->toBeFalse();
        expect(is_file($download))->toBeFalse();
    } finally {
        if (is_file($download)) {
            unlink($download);
        }
        removeCloudAgentFixture($directory);
    }
});

it('keeps a download whose digest matches the pin', function () {
    $directory = cloudAgentFixture();
    $download = $directory.'/payload.bin';
    file_put_contents($download, "pinned-bytes\n");

    try {
        $process = new Process(
            [
                'bash', '-c', 'source "$1" && digest="$(sha256sum "$2" | awk \'{print $1}\')" && verify_digest sha256 "$2" "$digest" && test -f "$2"',
                'bash',
                base_path('.cursor/agent-env.sh'),
                $download,
            ],
            $directory,
            ['PATH' => getenv('PATH') ?: '/usr/bin:/bin', 'HOME' => $directory],
        );
        $process->run();

        expect($process->getExitCode().$process->getErrorOutput())->toBe('0');
        expect(is_file($download))->toBeTrue();
    } finally {
        if (is_file($download)) {
            unlink($download);
        }
        removeCloudAgentFixture($directory);
    }
});

it('checks the composer installer before executing it', function () {
    $install = file_get_contents(base_path('.cursor/install.sh'));
    $pin = 'c8b085408188070d5f52bcfe4ecfbee5f727afa458b2573b8eaaf77b3419b0bf2768dc67c86944da1544f06fa544fd47';

    expect($install)->toContain('COMPOSER_INSTALLER_SHA384="'.$pin.'"');

    $download = strpos($install, 'https://getcomposer.org/installer');
    $verify = strpos($install, 'verify_digest sha384 "${installer}" "${COMPOSER_INSTALLER_SHA384}"');
    $execute = strpos($install, 'php "${installer}"');

    expect($download)->toBeInt();
    expect($verify)->toBeInt();
    expect($execute)->toBeInt();
    expect($download)->toBeLessThan($verify);
    expect($verify)->toBeLessThan($execute);
});

it('checks the node tarball before extracting it', function () {
    $install = file_get_contents(base_path('.cursor/install.sh'));
    $pin = '1805bcd35e6815559bd2eb97db438d3fdb0eb3a5e6a977b7c1badca76337e9ce';

    expect($install)->toContain('NODE_LINUX_X64_SHA256="'.$pin.'"');

    $download = strpos($install, 'node-v${version}-linux-x64.tar.xz');
    $verify = strpos($install, 'verify_digest sha256 "${tarball}" "${NODE_LINUX_X64_SHA256}"');
    $extract = strpos($install, 'sudo tar -xJf "${tarball}"');

    expect($download)->toBeInt();
    expect($verify)->toBeInt();
    expect($extract)->toBeInt();
    expect($download)->toBeLessThan($verify);
    expect($verify)->toBeLessThan($extract);
});
