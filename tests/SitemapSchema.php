<?php

declare(strict_types=1);

namespace Tests;

use DOMDocument;
use LibXMLError;

/** Checks a sitemap document against the protocol schema shipped in fixtures. */
final class SitemapSchema
{
    public static function assertValid(string $xml, string $schema): void
    {
        $dom = new DOMDocument;
        expect($dom->loadXML($xml))->toBeTrue();

        $previous = libxml_use_internal_errors(true);
        libxml_clear_errors();
        $valid = $dom->schemaValidate(base_path('tests/fixtures/sitemap/'.$schema));
        $errors = array_map(static fn (LibXMLError $error): string => trim($error->message), libxml_get_errors());
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        expect($valid)->toBeTrue()->and($errors)->toBe([]);
    }
}
