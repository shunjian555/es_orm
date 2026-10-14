<?php

declare(strict_types=1);

namespace EsOrm\Query\Pagination;

use ArrayIterator;
use Countable;
use EsOrm\Model\ModelCollection;
use IteratorAggregate;
use JsonSerializable;
use Traversable;

/**
 * A cursor based page, built on Elasticsearch `search_after`.
 *
 * Unlike {@see LengthAwarePaginator} it does not know the total number of
 * documents; instead it exposes the cursor (the sort values of the last hit)
 * that the caller must pass back to fetch the next page. This is the recommended
 * way to paginate beyond the `from + size` window limit (10 000 by default).
 *
 * @implements IteratorAggregate<int, object>
 */
final class CursorPaginator implements Countable, IteratorAggregate, JsonSerializable
{
    /**
     * @param array<int|string, mixed>|null $nextCursor
     */
    public function __construct(
        private readonly ModelCollection $items,
        private readonly int $perPage,
        private readonly ?array $nextCursor,
        private readonly bool $hasMore,
    ) {
    }

    public function items(): ModelCollection
    {
        return $this->items;
    }

    public function perPage(): int
    {
        return $this->perPage;
    }

    /**
     * The cursor to pass to `searchAfter()` for the next page, or null when the
     * last page has been reached.
     *
     * @return array<int|string, mixed>|null
     */
    public function nextCursor(): ?array
    {
        return $this->nextCursor;
    }

    public function hasMorePages(): bool
    {
        return $this->hasMore;
    }

    public function isEmpty(): bool
    {
        return $this->items->isEmpty();
    }

    public function first(): ?object
    {
        return $this->items->first();
    }

    public function last(): ?object
    {
        return $this->items->last();
    }

    public function count(): int
    {
        return $this->items->count();
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'data' => $this->items->toArray(),
            'per_page' => $this->perPage,
            'next_cursor' => $this->nextCursor,
            'has_more' => $this->hasMore,
        ];
    }

    public function toJson(int $options = 0): string
    {
        return json_encode($this->toArray(), $options | JSON_THROW_ON_ERROR);
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->items->all());
    }
}
