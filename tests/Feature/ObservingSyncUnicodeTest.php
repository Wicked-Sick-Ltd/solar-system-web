<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Http\Middleware\PrivateObservingSync;
use App\Models\User;
use App\Services\Observing\ObservingAccountScope;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use Tests\TestCase;

// Pest's normal launcher does not set PHPUnit's child-process autoload path.
if (! defined('PHPUNIT_COMPOSER_INSTALL')) {
    define('PHPUNIT_COMPOSER_INSTALL', dirname(__DIR__, 2).'/vendor/autoload.php');
}

final class ObservingSyncUnicodeTest extends TestCase
{
    use RefreshDatabase;

    // Exercise the large transport regression with a fresh PHP memory budget,
    // independent of applications retained by hundreds of earlier tests.
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function test_valid_unicode_backup_fits_decoded_transport_limit(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->withHeader('X-Observing-Account', ObservingAccountScope::forUser($user));
        $payload = json_decode(file_get_contents(base_path('tests/fixtures/observing-sync/payload.json')), true, flags: JSON_THROW_ON_ERROR);
        $observation = $payload['journal']['observations'][0];
        $observation['equipmentAndSite'] = null;
        $observation['notes'] = str_repeat('🪐', 2000);
        $payload['journal']['observations'] = [];
        for ($i = 100; $i < 170; $i++) {
            $payload['journal']['observations'][] = [...$observation, 'id' => sprintf('00000000-0000-4000-8000-%012d', $i)];
        }
        // Match browser JSON.stringify, rather than the test helper's ASCII escapes.
        $raw = json_encode(['expectedRevision' => 0, 'payload' => $payload], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        $this->call('PUT', '/account/observing-workspace', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_OBSERVING_ACCOUNT' => ObservingAccountScope::forUser(auth()->user()),
        ], $raw)->assertOk();
        $download = $this->getJson('/account/observing-workspace')->assertOk();
        $this->assertLessThan(PrivateObservingSync::MAX_BYTES, strlen($download->getContent()));
        $this->assertSame($observation['notes'], $download->json('payload.journal.observations.69.notes'));
    }
}
