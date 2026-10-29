<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Parses an optional list of egress ranges. Blank input is an empty list.
 * Invalid input is null. Stored values are canonical network addresses.
 */
final class CidrList
{
    public const int MAX_RANGES = 20;

    public const int MAX_LENGTH = 2000;

    /**
     * @return list<string>|null
     */
    public static function parse(?string $raw): ?array
    {
        if ($raw === null || trim($raw) === '') {
            return [];
        }

        if (strlen($raw) > self::MAX_LENGTH) {
            return null;
        }

        $parts = preg_split('/[\s,;]+/', trim($raw), -1, PREG_SPLIT_NO_EMPTY);
        if ($parts === false || $parts === [] || count($parts) > self::MAX_RANGES) {
            return null;
        }

        $ranges = [];
        foreach ($parts as $part) {
            $normalized = self::normalize($part);
            if ($normalized === null) {
                return null;
            }
            $ranges[] = $normalized;
        }

        return array_values(array_unique($ranges));
    }

    public static function normalize(string $cidr): ?string
    {
        $cidr = trim($cidr);
        if (substr_count($cidr, '/') !== 1) {
            return null;
        }

        [$address, $prefixText] = explode('/', $cidr, 2);
        if ($prefixText === '' || preg_match('/^(0|[1-9][0-9]*)$/', $prefixText) !== 1) {
            return null;
        }

        $prefix = (int) $prefixText;
        $packed = @inet_pton($address);
        if ($packed === false) {
            return null;
        }

        $bits = strlen($packed) * 8;
        if ($prefix < 1 || $prefix > $bits) {
            return null;
        }

        $bytes = array_values(unpack('C*', $packed));
        foreach ($bytes as $index => $byte) {
            $start = $index * 8;
            if ($prefix <= $start) {
                $bytes[$index] = 0;
            } elseif ($prefix < $start + 8) {
                $keep = $prefix - $start;
                $bytes[$index] = $byte & ((0xFF << (8 - $keep)) & 0xFF);
            }
        }

        $network = inet_ntop(pack('C*', ...$bytes));
        if ($network === false) {
            return null;
        }

        return $network.'/'.$prefix;
    }
}
