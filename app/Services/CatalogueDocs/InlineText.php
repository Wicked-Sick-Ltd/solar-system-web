<?php

declare(strict_types=1);

namespace App\Services\CatalogueDocs;

/**
 * Escapes catalogue text, then restores a tiny inline subset (bold and code)
 * that the OpenAPI description actually uses.
 */
final class InlineText
{
    public static function html(string $text): string
    {
        $text = trim($text);
        if (strlen($text) > 4000) {
            $text = substr($text, 0, 4000).'…';
        }

        $escaped = e($text);
        $escaped = preg_replace('/\*\*([^*]+)\*\*/', '<strong>$1</strong>', $escaped) ?? $escaped;
        $escaped = preg_replace('/`([^`]+)`/', '<code>$1</code>', $escaped) ?? $escaped;

        return nl2br($escaped, false);
    }
}
