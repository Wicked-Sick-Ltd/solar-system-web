<?php

use App\Mail\SiteFeedback;
use App\Services\Feedback\FeedbackDelivery;
use Illuminate\Support\Facades\Mail;

beforeEach(function (): void {
    fakeSolar();
    config(['feedback.mailer' => 'smtp', 'feedback.to' => 'hello@publicuniverse.net']);
    Mail::fake();
});

it('offers private accessible feedback without requiring an account', function (): void {
    $this->get(route('feedback'))->assertOk()
        ->assertSee('Feature request')->assertSee('Report a bug')->assertSee('Get in contact')
        ->assertSee('name="_token"', false)->assertSee('hello@publicuniverse.net')
        ->assertHeader('Cache-Control', 'no-store, private')->assertHeader('Referrer-Policy', 'no-referrer');
});

it('sends only the selected content to the private inbox with an optional reply address', function (string $category): void {
    $this->post(route('feedback.store'), [
        'category' => $category, 'message' => 'Please add more classroom exercises.',
        'email' => 'teacher@example.org', 'to' => 'attacker@example.org', 'location' => 'do not attach',
    ])->assertRedirect(route('feedback'))->assertSessionHas('feedback_sent', true)
        ->assertHeader('Cache-Control', 'no-store, private');

    Mail::assertSent(SiteFeedback::class, function (SiteFeedback $mail) use ($category): bool {
        return $mail->hasTo('hello@publicuniverse.net') && ! $mail->hasTo('attacker@example.org')
            && $mail->category === config('feedback.categories.'.$category)
            && $mail->feedbackText === 'Please add more classroom exercises.'
            && $mail->envelope()->replyTo[0]->address === 'teacher@example.org';
    });
    Mail::assertSentCount(1);
})->with(['feature', 'bug', 'contact']);

it('allows feedback without an email or account', function (): void {
    $this->post(route('feedback.store'), ['category' => 'contact', 'message' => 'Thank you for the planet data.'])
        ->assertSessionHas('feedback_sent', true);
    Mail::assertSent(SiteFeedback::class, fn (SiteFeedback $mail): bool => $mail->replyEmail === null && $mail->envelope()->replyTo === []);
});

it('validates input and never mails invalid or spam submissions', function (array $input, string $error): void {
    $this->post(route('feedback.store'), array_replace(['category' => 'bug', 'message' => 'The page does not load.'], $input))
        ->assertRedirect(route('feedback'))->assertSessionHasErrors($error)->assertSessionMissing('feedback_sent');
    Mail::assertNothingSent();
})->with([
    [['category' => 'other'], 'category'],
    [['category' => ['bug']], 'category'],
    [['message' => 'short'], 'message'],
    [['message' => str_repeat('x', 5001)], 'message'],
    [['message' => ['not a string']], 'message'],
    [['email' => "a@example.org\r\nBcc:other@example.org"], 'email'],
    [['website' => 'https://spam.example'], 'website'],
]);

it('bounds flashed drafts and ignores unrelated request fields', function (): void {
    $this->post(route('feedback.store'), ['category' => 'bug', 'message' => str_repeat('x', 6000), 'secret' => 'never flash'])
        ->assertSessionHasErrors('message')->assertSessionMissing('_old_input.secret')
        ->assertSessionHas('_old_input.message', str_repeat('x', 5000));
});

it('does not treat non-delivery or logging transports as mail delivery', function (string $mailer): void {
    config(['feedback.mailer' => $mailer]);
    $this->get(route('feedback'))->assertOk()->assertSee('The form is temporarily unavailable.')->assertSee('disabled', false);
    $this->post(route('feedback.store'), ['category' => 'bug', 'message' => 'The page does not load.'])
        ->assertSessionHasErrors('delivery')->assertSessionMissing('feedback_sent');
    Mail::assertNothingSent();
})->with(['log', 'array', 'failover', 'missing']);

it('preserves a bounded draft and hides transport errors on delivery failure', function (): void {
    $this->mock(FeedbackDelivery::class, function ($mock): void {
        $mock->shouldReceive('isConfigured')->once()->andReturn(true);
        $mock->shouldReceive('send')->once()->andThrow(new RuntimeException('secret SMTP transcript'));
    });
    $this->post(route('feedback.store'), ['category' => 'contact', 'message' => 'Please reply when possible.'])
        ->assertSessionHasErrors('delivery')->assertSessionMissing('feedback_sent')
        ->assertSessionHas('_old_input.message', 'Please reply when possible.');
    expect(session('errors')->first('delivery'))->not->toContain('secret SMTP');
});

it('rate limits repeated submissions without sending more mail', function (): void {
    $input = ['category' => 'feature', 'message' => 'Please add more classroom exercises.'];
    for ($i = 0; $i < 3; $i++) {
        $this->post(route('feedback.store'), $input)->assertRedirect();
    }
    $this->post(route('feedback.store'), $input)->assertStatus(429)->assertHeader('Cache-Control', 'no-store, private');
    Mail::assertSentCount(3);
});

it('preserves literal bug reports in plain text without embedding them in headers', function (): void {
    $mail = new SiteFeedback('Report a bug', '<script>alert("test")</script>', 'visitor@example.org');
    expect($mail->content()->text)->toBe('mail.site-feedback');
    expect($mail->content()->html)->toBeNull();
    expect(view('mail.site-feedback', ['category' => $mail->category, 'replyEmail' => $mail->replyEmail, 'feedbackText' => $mail->feedbackText])->render())->toContain('<script>alert("test")</script>');
    expect($mail->envelope()->subject)->toBe('[Public Universe] Report a bug');
});

it('rejects effective log transports hidden by URL or legacy configuration', function (array $overrides): void {
    config($overrides);
    expect(app(FeedbackDelivery::class)->isConfigured())->toBeFalse();
    $this->post(route('feedback.store'), ['category' => 'contact', 'message' => 'Keep this message private.'])
        ->assertSessionHasErrors('delivery')->assertSessionMissing('feedback_sent');
    Mail::assertNothingSent();
})->with([
    [['mail.mailers.smtp.url' => 'log://default']],
    [['mail.mailers.smtp.url' => 'array://default']],
    [['mail.driver' => 'log']],
]);

it('requires CSRF and keeps session failure responses private', function (): void {
    $this->app->instance('env', 'production');
    $this->post(route('feedback.store'), ['category' => 'bug', 'message' => 'The page does not load.'])
        ->assertStatus(419)->assertHeader('Cache-Control', 'no-store, private')->assertHeader('Referrer-Policy', 'no-referrer');
    Mail::assertNothingSent();
});

it('keeps feedback limits separate from observing requests', function (): void {
    for ($i = 0; $i < 3; $i++) {
        $this->post('/observe/shortlist', [])->assertStatus(422);
    }
    $this->post(route('feedback.store'), ['category' => 'contact', 'message' => 'Thank you for the classroom tools.'])
        ->assertSessionHas('feedback_sent', true);
    Mail::assertSentCount(1);
});
