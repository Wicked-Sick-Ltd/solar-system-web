<?php

declare(strict_types=1);

namespace App\Services\Observing;

use App\Models\User;

final class ObservingAccountScope
{
    public static function forUser(User $user): string
    {
        return hash_hmac('sha256', 'observing-sync:user:'.$user->getAuthIdentifier(), (string) config('app.key'));
    }
}
