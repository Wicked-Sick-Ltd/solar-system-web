<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Single-line plain text for values placed in mail subjects and Markdown
 * mail lines. Names come from registrants and the catalogue API, so they must
 * not be able to start a new Markdown block or form link, image or HTML syntax.
 */
final class MailText
{
    /** Characters that open Markdown links, images, autolinks, raw HTML or escapes. */
    public const array MARKUP = ['[', ']', '<', '>', '\\'];

    /** Matches line breaks, control characters and Markdown markup in a display name. */
    public const string DISPLAY_NAME_FORBIDDEN = '/[\p{Cc}\p{Zl}\p{Zp}\[\]<>\\\\]/u';

    public static function inline(string $value): string
    {
        $value = mb_scrub($value, 'UTF-8');
        $value = preg_replace('/[\p{Cc}\p{Zl}\p{Zp}]+/u', ' ', $value) ?? '';
        $value = str_replace(self::MARKUP, '', $value);

        return trim(preg_replace('/\s{2,}/u', ' ', $value) ?? '');
    }
}
