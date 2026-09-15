<?php

declare(strict_types=1);

namespace App\Services\Mailchimp;

enum SubscribeResult: string
{
    /** Added (or re-added) with double opt-in; Mailchimp has emailed a confirmation. */
    case Pending = 'pending';

    /** Already a confirmed member of the audience. */
    case AlreadySubscribed = 'subscribed';
}
