<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Services\Mailchimp\MailchimpClient;
use App\Services\Mailchimp\MailchimpException;
use App\Services\Mailchimp\SubscribeResult;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Component;

/**
 * Footer newsletter form. Double opt-in via Mailchimp, so the address never
 * touches local storage. A honeypot field and a per-IP rate limit keep the
 * obvious bots out without a CAPTCHA.
 */
final class NewsletterSignup extends Component
{
    public string $email = '';

    /** Honeypot — hidden from humans, filled by bots. */
    public string $website = '';

    /** idle | pending | subscribed | error */
    public string $state = 'idle';

    public function subscribe(MailchimpClient $mailchimp): void
    {
        $key = 'newsletter:'.(request()->ip() ?? 'unknown');

        if (RateLimiter::tooManyAttempts($key, 5)) {
            $this->addError('email', __('Too many attempts — please try again in a minute.'));

            return;
        }
        RateLimiter::hit($key, 60);

        $this->validate(['email' => ['required', 'email:rfc', 'max:254']]);

        // Bots that fill the hidden field get a convincing "success" and nothing else.
        if ($this->website !== '') {
            $this->state = 'pending';

            return;
        }

        try {
            $this->state = match ($mailchimp->subscribe($this->email)) {
                SubscribeResult::Pending => 'pending',
                SubscribeResult::AlreadySubscribed => 'subscribed',
            };
        } catch (MailchimpException $e) {
            Log::warning('Newsletter signup failed');
            $this->state = 'error';
        }
    }

    public function render(): View
    {
        return view('livewire.newsletter-signup');
    }
}
