<?php

declare(strict_types=1);

namespace App\Services\Feedback;

use App\Mail\SiteFeedback;
use Illuminate\Support\ConfigurationUrlParser;
use Illuminate\Support\Facades\Mail;
use Throwable;

class FeedbackDelivery
{
    public function isConfigured(): bool
    {
        $mailer = (string) config('feedback.mailer');
        $mailConfig = config('mail.driver') ? config('mail') : config('mail.mailers.'.$mailer);
        if (! is_array($mailConfig)) {
            return false;
        }
        try {
            // Match MailManager's effective configuration, including MAIL_URL overrides.
            $transport = isset($mailConfig['url'])
                ? (new ConfigurationUrlParser)->parseConfiguration($mailConfig)['driver'] ?? null
                : $mailConfig['transport'] ?? config('mail.driver');
        } catch (Throwable) {
            return false;
        }

        // Never send private messages into logs or silently succeed via a log fallback.
        return in_array($transport, ['smtp', 'sendmail', 'ses', 'ses-v2', 'mailgun', 'postmark', 'resend'], true)
            && filter_var(config('feedback.to'), FILTER_VALIDATE_EMAIL) !== false;
    }

    public function send(SiteFeedback $message): void
    {
        Mail::mailer((string) config('feedback.mailer'))
            ->to((string) config('feedback.to'))->send($message);
    }
}
