<?php

declare(strict_types=1);

use App\Livewire\SkyObserver;
use App\Models\User;
use App\Models\VisibilityAlert;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Symfony\Component\Mime\Email;
use Tests\CatalogueObservation;

uses(RefreshDatabase::class);

beforeEach(fn () => fakeSolar());

/**
 * Display name a visitor can submit on the public registration form.
 * The newline lets the link and image leave the greeting heading.
 */
function markdownDisplayName(): string
{
    return "Victim\n[Review this alert](https://attacker.example/phish)\n![pixel](https://attacker.example/pixel.png)";
}

/** Catalogue name the sky endpoint can return into the same mail line and subject. */
function markdownObjectName(): string
{
    return 'Saturn [chart](https://attacker.example/object) ![pixel](https://attacker.example/object.png)';
}

function saveVisibilityAlert(User $user, string $objectId = 'planet-saturn'): void
{
    test()->actingAs($user);

    Livewire::test(SkyObserver::class, ['objectId' => $objectId])
        ->call('setLocation', 51.5, -0.12)
        ->call('saveAlert')
        ->assertSet('alertSaved', true);

    expect($user->visibilityAlerts()->count())->toBe(1);
}

/**
 * Http::fake() appends stubs, and the suite stub already answers /sky/.
 * Swap in a fresh factory so this response is the one the client accepts.
 */
function fakeSkyNamed(string $name): void
{
    Http::swap(new HttpFactory);
    Cache::flush();
    CatalogueObservation::prime();

    Http::fake(function ($request) use ($name) {
        $path = parse_url($request->url(), PHP_URL_PATH) ?? '';

        if (str_contains($path, '/sky/')) {
            $payload = skyPayload(observer: isset($request['lat']));
            $payload['name'] = $name;

            return Http::response($payload);
        }

        if (str_contains($path, '/v1/forecast')) {
            return Http::response(openMeteoPayload());
        }

        return Http::response(['detail' => 'unused'], 404);
    });
}

function deliveredAlertMail(): Email
{
    $sent = Mail::mailer()->getSymfonyTransport()->messages()->sole();
    $message = $sent->getOriginalMessage();
    expect($message)->toBeInstanceOf(Email::class);

    return $message;
}

it('registers an ordinary display name and signs that person in', function () {
    $this->post('/register', [
        'name' => 'Ada Lovelace',
        'email' => 'ada@example.com',
        'password' => 'password1234',
        'password_confirmation' => 'password1234',
    ])->assertRedirect();

    $this->assertAuthenticated();
    $this->assertDatabaseHas('users', [
        'name' => 'Ada Lovelace',
        'email' => 'ada@example.com',
        'email_verified_at' => null,
    ]);
});

it('rejects a registration display name that contains a newline and markdown', function () {
    $this->post('/register', [
        'name' => markdownDisplayName(),
        'email' => 'victim@example.com',
        'password' => 'password1234',
        'password_confirmation' => 'password1234',
    ])->assertSessionHasErrors('name');

    $this->assertGuest();
    $this->assertDatabaseMissing('users', ['email' => 'victim@example.com']);
});

it('does not turn a stored display name into a link or image in the visibility alert', function () {
    $user = User::factory()->create([
        'name' => markdownDisplayName(),
        'email' => 'victim@example.com',
        'email_verified_at' => null,
    ]);
    saveVisibilityAlert($user);

    $this->artisan('alerts:send-visibility')->assertSuccessful();

    $mail = deliveredAlertMail();
    $html = (string) $mail->getHtmlBody();

    expect($mail->getTo()[0]->getAddress())->toBe('victim@example.com')
        ->and($html)->toContain('Review this alert')
        ->and($html)->not->toMatch('/<a\b[^>]*\bhref\s*=\s*["\']https:\/\/attacker\.example\/phish/i')
        ->and($html)->not->toMatch('/<img\b[^>]*\bsrc\s*=\s*["\']https:\/\/attacker\.example\/pixel\.png/i');
});

it('does not turn a catalogue object name into a link or image in the visibility alert', function () {
    fakeSkyNamed(markdownObjectName());

    $user = User::factory()->create(['name' => 'Ada Lovelace', 'email' => 'ada@example.com']);
    saveVisibilityAlert($user);

    $this->artisan('alerts:send-visibility')->assertSuccessful();

    $mail = deliveredAlertMail();
    $html = (string) $mail->getHtmlBody();

    expect($html)->toContain('chart')
        ->and($html)->not->toMatch('/<a\b[^>]*\bhref\s*=\s*["\']https:\/\/attacker\.example\/object["\']/i')
        ->and($html)->not->toMatch('/<img\b[^>]*\bsrc\s*=\s*["\']https:\/\/attacker\.example\/object\.png/i')
        ->and($mail->getSubject())->not->toContain('](https://attacker.example/object')
        ->and($mail->getSubject())->not->toContain("\n")
        ->and($mail->getSubject())->not->toContain("\r");
});

it('still sends a visibility alert for an ordinary display name and catalogue name', function () {
    $user = User::factory()->create(['name' => 'Ada Lovelace', 'email' => 'ada@example.com']);
    saveVisibilityAlert($user);

    $this->artisan('alerts:send-visibility')->assertSuccessful();

    $mail = deliveredAlertMail();
    $html = (string) $mail->getHtmlBody();

    expect($mail->getTo()[0]->getAddress())->toBe('ada@example.com')
        ->and($mail->getSubject())->toBe('Solar alert: Saturn is up after dark')
        ->and($html)->toContain('Ada Lovelace')
        ->and($html)->toContain('Saturn')
        ->and($html)->not->toMatch('/<a\b[^>]*\bhref\s*=\s*["\']https:\/\/attacker\.example/i');

    expect(VisibilityAlert::query()->where('user_id', $user->id)->first()->last_state_up_after_dark)->toBeTrue();
});
