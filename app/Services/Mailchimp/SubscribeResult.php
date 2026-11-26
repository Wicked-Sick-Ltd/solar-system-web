<?php

declare(strict_types=1);

namespace App\Services\Mailchimp;

enum SubscribeResult: string
{
    /** Added (or re-added) with double opt-in; Mailchimp has emailed a confirmation. */
    case Pending = 'pending';

    /**
     * Already a confirmed member of the audience. Mailchimp leaves the member
     * as it is and sends no email. The signup form answers exactly as it does
     * for Pending, so it never reveals list membership.
     */
    case AlreadySubscribed = 'subscribed';
}
