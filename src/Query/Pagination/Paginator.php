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
 * A simple page: it only knows whether more pages exist, mirroring
 * `Illuminate\Pagination\Paginator`.
 *
 * @implements IteratorAggregate<int, object>
 */
final class Paginator implements Countable, IteratorAggregate, JsonSerializable
{
    public function __construct(
        private readonly ModelCollection $items,
        private readonly int $perPage,
        private readonly int $currentPage,
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

    public function currentPage(): int
    {
        return $this->currentPage;
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
            'current_page' => $this->currentPage,
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
