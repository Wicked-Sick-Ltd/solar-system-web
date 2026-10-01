<?php

declare(strict_types=1);

namespace App\Services\SolarApi\Data;

/**
 * A page of catalogue rows. The backend returns no total count, so paging is
 * cursor-style: we ask for one row more than requested to learn whether a next
 * page exists, then trim it off.
 *
 * @template T
 */
final readonly class Paginated
{
    /**
     * @param  list<T>  $items
     */
    public function __construct(
        public array $items,
        public int $limit,
        public int $offset,
        public bool $hasMore,
        public ?string $nextAfter = null,
        public ?CatalogueSnapshot $catalogueSnapshot = null,
    ) {}

    public function isEmpty(): bool
    {
        return $this->items === [];
    }

    public function count(): int
    {
        return count($this->items);
    }

    public function hasPrevious(): bool
    {
        return $this->offset > 0;
    }

    public function previousOffset(): int
    {
        return max(0, $this->offset - $this->limit);
    }

    public function nextOffset(): int
    {
        return $this->offset + $this->limit;
    }

    /** 1-based index of the first row on this page (for "showing X–Y" copy). */
    public function from(): int
    {
        return $this->isEmpty() ? 0 : $this->offset + 1;
    }

    public function to(): int
    {
        return $this->offset + $this->count();
    }
}
