<?php

declare(strict_types=1);

namespace EsOrm\Contracts;

use ArrayAccess;
use Countable;
use IteratorAggregate;
use JsonSerializable;

/**
 * A collection of (usually) models, inspired by `Illuminate\Support\Collection`
 * but completely independent from Laravel.
 *
 * @template TKey of array-key
 * @template TValue of object
 * @extends ArrayAccess<TKey, TValue>
 * @extends IteratorAggregate<TKey, TValue>
 */
interface CollectionInterface extends ArrayAccess, Countable, IteratorAggregate, JsonSerializable
{
    /**
     * All items as a plain PHP array.
     *
     * @return array<TKey, TValue>
     */
    public function all(): array;

    /**
     * First item, or null when the collection is empty.
     *
     * @return TValue|null
     */
    public function first(): ?object;

    /**
     * Last item, or null when the collection is empty.
     *
     * @return TValue|null
     */
    public function last(): ?object;

    public function isEmpty(): bool;

    public function isNotEmpty(): bool;

    /**
     * @param callable(TValue, TKey): mixed $callback
     * @return static
     */
    public function map(callable $callback): static;

    /**
     * @param callable(TValue, TKey): bool|null $callback
     * @return static
     */
    public function filter(?callable $callback = null): static;

    /**
     * @param callable(TValue, TKey): mixed $callback
     * @return static
     */
    public function each(callable $callback): static;

    /**
     * @return array<TKey, mixed>
     */
    public function pluck(string $value, ?string $key = null): array;

    /**
     * @param array<array-key, string>|string $keys
     * @return static
     */
    public function only(array|string $keys): static;

    /**
     * @param array<array-key, string>|string $keys
     * @return static
     */
    public function except(array|string $keys): static;

    public function contains(mixed $value): bool;

    /**
     * @param callable(TValue, TValue): int|null $callback
     * @return static
     */
    public function sort(?callable $callback = null): static;

    /**
     * @return static
     */
    public function sortBy(string $key, int $options = SORT_REGULAR, bool $descending = false): static;

    /**
     * Re-index the collection with sequential integer keys.
     *
     * @return static
     */
    public function values(): static;

    /**
     * @return array<TKey, array<string, mixed>|scalar|null>
     */
    public function toArray(): array;

    public function toJson(int $options = 0): string;
}
