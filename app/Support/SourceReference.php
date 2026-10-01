<?php

declare(strict_types=1);

namespace App\Support;

final class SourceReference
{
    /** Display upstream citation markup as text; callers must still HTML-escape it. */
    public static function plainText(?string $reference): ?string
    {
        if ($reference === null) {
            return null;
        }

        $text = strip_tags(html_entity_decode($reference, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        $text = trim(preg_replace('/\s+/u', ' ', $text) ?? $text);

        return $text === '' ? null : $text;
    }
}
