<?php

declare(strict_types=1);

namespace App\Services\Sitemap;

/**
 * Sitemap protocol documents. Each urlset stays inside the protocol ceilings
 * (50,000 URLs and 50MB uncompressed) by splitting into successive parts.
 */
final class SitemapXml
{
    public const MAX_URLS = 50000;

    public const MAX_BYTES = 52428800;

    public const NAMESPACE = 'http://www.sitemaps.org/schemas/sitemap/0.9';

    /**
     * @param  list<array{loc:string,lastmod:?string,priority:?string}>  $entries
     * @return list<list<array{loc:string,lastmod:?string,priority:?string}>>
     */
    public static function chunks(array $entries, int $maxUrls = self::MAX_URLS, int $maxBytes = self::MAX_BYTES): array
    {
        $chunks = [];
        $batch = [];
        $bytes = self::envelopeBytes();

        foreach ($entries as $entry) {
            $rowBytes = strlen(self::url($entry));
            if ($batch !== [] && (count($batch) >= $maxUrls || $bytes + $rowBytes > $maxBytes)) {
                $chunks[] = $batch;
                $batch = [];
                $bytes = self::envelopeBytes();
            }
            $batch[] = $entry;
            $bytes += $rowBytes;
        }

        if ($batch !== []) {
            $chunks[] = $batch;
        }

        return $chunks;
    }

    /**
     * @param  list<array{loc:string,lastmod:?string,priority:?string}>  $entries
     */
    public static function urlset(array $entries): string
    {
        $body = '';
        foreach ($entries as $entry) {
            $body .= self::url($entry);
        }

        return '<?xml version="1.0" encoding="UTF-8"?>'."\n"
            .'<urlset xmlns="'.self::NAMESPACE.'">'."\n"
            .$body
            .'</urlset>'."\n";
    }

    /**
     * @param  list<array{loc:string,lastmod:?string}>  $sitemaps
     */
    public static function index(array $sitemaps): string
    {
        $body = '';
        foreach ($sitemaps as $sitemap) {
            $body .= '  <sitemap><loc>'.self::escape($sitemap['loc']).'</loc>';
            if ($sitemap['lastmod'] !== null) {
                $body .= '<lastmod>'.self::escape($sitemap['lastmod']).'</lastmod>';
            }
            $body .= "</sitemap>\n";
        }

        return '<?xml version="1.0" encoding="UTF-8"?>'."\n"
            .'<sitemapindex xmlns="'.self::NAMESPACE.'">'."\n"
            .$body
            .'</sitemapindex>'."\n";
    }

    /** @param array{loc:string,lastmod:?string,priority:?string} $entry */
    public static function url(array $entry): string
    {
        $row = '  <url><loc>'.self::escape($entry['loc']).'</loc>';
        if ($entry['lastmod'] !== null) {
            $row .= '<lastmod>'.self::escape($entry['lastmod']).'</lastmod>';
        }
        if ($entry['priority'] !== null) {
            $row .= '<priority>'.self::escape($entry['priority']).'</priority>';
        }

        return $row."</url>\n";
    }

    private static function envelopeBytes(): int
    {
        return strlen(self::urlset([]));
    }

    private static function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }
}
