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
 * A length aware page: it knows the total number of matching documents and can
 * therefore compute the last page number.
 *
 * It mirrors the common parts of `Illuminate\Pagination\LengthAwarePaginator`
 * without depending on Laravel.
 *
 * @implements IteratorAggregate<int, object>
 */
final class LengthAwarePaginator implements Countable, IteratorAggregate, JsonSerializable
{
    public function __construct(
        private readonly ModelCollection $items,
        private readonly int $total,
        private readonly int $perPage,
        private readonly int $currentPage,
    ) {
    }

    public function items(): ModelCollection
    {
        return $this->items;
    }

    public function total(): int
    {
        return $this->total;
    }

    public function perPage(): int
    {
        return $this->perPage;
    }

    public function currentPage(): int
    {
        return $this->currentPage;
    }

    public function lastPage(): int
    {
        if ($this->perPage <= 0) {
            return 1;
        }

        return max(1, (int) ceil($this->total / $this->perPage));
    }

    public function hasMorePages(): bool
    {
        return $this->currentPage < $this->lastPage();
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
            'total' => $this->total,
            'per_page' => $this->perPage,
            'current_page' => $this->currentPage,
            'last_page' => $this->lastPage(),
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
