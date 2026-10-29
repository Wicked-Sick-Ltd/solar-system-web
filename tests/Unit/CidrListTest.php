<?php

declare(strict_types=1);

use App\Support\CidrList;

it('canonicalises network addresses and drops host bits', function (): void {
    expect(CidrList::parse("203.0.113.5/24\n2001:db8::1/48, 198.51.100.0/24"))
        ->toBe(['203.0.113.0/24', '2001:db8::/48', '198.51.100.0/24']);
});

it('treats a blank list as no ranges', function (): void {
    expect(CidrList::parse(null))->toBe([])
        ->and(CidrList::parse('   '))->toBe([]);
});

it('rejects ranges that are not a usable CIDR', function (string $raw): void {
    expect(CidrList::parse($raw))->toBeNull();
})->with([
    '203.0.113.5',
    '203.0.113.0/33',
    '2001:db8::/129',
    '0.0.0.0/0',
    '::/0',
    'not-a-cidr',
    '203.0.113.0/24, 999.1.1.0/24',
    implode("\n", array_map(fn (int $i): string => "203.0.113.{$i}/32", range(1, 21))),
]);
