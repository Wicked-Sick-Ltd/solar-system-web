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
        // The response, including the analytics dispatch, matches a real sign-up.
        if ($this->website !== '') {
            $this->state = 'pending';
            $this->dispatchSignupEvent();

            return;
        }

        try {
            $result = $mailchimp->subscribe($this->email);
            $this->state = match ($result) {
                SubscribeResult::Pending => 'pending',
                SubscribeResult::AlreadySubscribed => 'subscribed',
            };
            if ($result === SubscribeResult::Pending) {
                $this->dispatchSignupEvent();
            }
        } catch (MailchimpException $e) {
            Log::warning('Newsletter signup failed');
            $this->state = 'error';
        }
    }

    /**
     * GA4 newsletter_signup (resources/js/analytics-consent.js) for every
     * "Check your inbox" outcome: a new or repeated double opt-in, and the
     * honeypot. Sending it for all of them keeps the response identical, so it
     * cannot reveal whether an address was already pending. Already
     * subscribed, validation errors and Mailchimp failures send nothing. The
     * params carry no address or other personal data.
     */
    private function dispatchSignupEvent(): void
    {
        $this->dispatch('pu-analytics', name: 'newsletter_signup', source: 'footer', method: 'mailchimp_double_opt_in');
    }

    public function render(): View
    {
        return view('livewire.newsletter-signup');
    }
}
