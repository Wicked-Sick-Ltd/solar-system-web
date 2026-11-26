<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Services\Mailchimp\MailchimpClient;
use App\Services\Mailchimp\MailchimpException;
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

    /**
     * idle | pending | error. An already-confirmed address also ends in
     * pending: the form must not tell anyone who is on the list.
     */
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
            // SubscribeResult::Pending and ::AlreadySubscribed get the same
            // "Check your inbox" response, event included, so the reply never
            // says whether the address is already on the list. Mailchimp's
            // add-or-update call leaves a confirmed member as it is and sends
            // it no email.
            $mailchimp->subscribe($this->email);
            $this->state = 'pending';
            $this->dispatchSignupEvent();
        } catch (MailchimpException $e) {
            Log::warning('Newsletter signup failed');
            $this->state = 'error';
        }
    }

    /**
     * GA4 newsletter_signup (resources/js/analytics-consent.js) for every
     * "Check your inbox" outcome: a new or repeated double opt-in, an address
     * that is already confirmed, and the honeypot. The event travels in the
     * Livewire response, so leaving it out for any of them would change the
     * response and reveal whether the address was already pending or
     * subscribed. Validation errors and Mailchimp failures send nothing. The
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
