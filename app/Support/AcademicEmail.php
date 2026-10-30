<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Academic inboxes are eligible to be flagged for the free institution key tier.
 * The flag is a review hint. It does not issue a key.
 */
final class AcademicEmail
{
    public static function isEligible(?string $email): bool
    {
        if (! is_string($email) || ! str_contains($email, '@')) {
            return false;
        }

        $at = strrpos($email, '@');
        if ($at === false || $at < 1) {
            return false;
        }

        $domain = strtolower(rtrim(substr($email, $at + 1), '.'));
        if ($domain === '' || preg_match('/^[a-z0-9.-]+$/', $domain) !== 1) {
            return false;
        }

        if ($domain === 'edu' || str_ends_with($domain, '.edu')) {
            return true;
        }

        return preg_match('/(?:^|\.)ac\.[a-z]{2}$/', $domain) === 1
            || preg_match('/(?:^|\.)edu\.[a-z]{2}$/', $domain) === 1;
    }
}
