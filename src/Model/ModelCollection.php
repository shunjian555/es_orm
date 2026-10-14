<?php

declare(strict_types=1);

namespace EsOrm\Model;

use ArrayIterator;
use Closure;
use EsOrm\Contracts\CollectionInterface;
use EsOrm\Support\Arr;
use InvalidArgumentException;
use JsonSerializable;
use Traversable;

/**
 * An immutable-ish collection of models.
 *
 * It behaves like `Illuminate\Support\Collection` for the common cases and
 * additionally carries the {@see SearchResult} metadata (total, aggregations,
 * suggesters, cursors) when it comes from a search request.
 *
 * @implements CollectionInterface<array-key, object>
 */
final class ModelCollection implements CollectionInterface
{
    /**
     * @var array<array-key, object>
     */
    private array $items;

    private ?SearchResult $result;

    /**
     * @param array<array-key, object> $items
     */
    public function __construct(array $items = [], ?SearchResult $result = null)
    {
        $this->items = $items;
        $this->result = $result;
    }

    /**
     * @param iterable<array-key, object> $items
     */
    public static function make(iterable $items = [], ?SearchResult $result = null): self
    {
        $normalised = [];

        foreach ($items as $key => $item) {
            $normalised[$key] = $item;
        }

        return new self($normalised, $result);
    }

    /**
     * The attached search metadata, when this collection is the result of a
     * `get()`/`search()` call.
     */
    public function result(): ?SearchResult
    {
        return $this->result;
    }

    /**
     * Attach the metadata of the search that produced this collection.
     */
    public function setResult(?SearchResult $result): static
    {
        $this->result = $result;

        return $this;
    }

    /**
     * Total number of matching documents (not necessarily the number of
     * hydrated items when pagination is used).
     */
    public function total(): int
    {
        return $this->result?->total() ?? count($this->items);
    }

    /**
     * @return array<string, mixed>
     */
    public function aggregations(): array
    {
        return $this->result?->aggregations() ?? [];
    }

    /**
     * A single aggregation result by name.
     */
    public function aggregation(string $name): mixed
    {
        return $this->result?->aggregation($name);
    }

    /**
     * @return array<string, mixed>
     */
    public function suggest(): array
    {
        return $this->result?->suggest() ?? [];
    }

    /**
     * The `search_after` cursor pointing after the last hydrated hit.
     *
     * @return array<int|string, mixed>
     */
    public function searchAfter(): array
    {
        return $this->result?->searchAfter() ?? [];
    }

    public function scrollId(): ?string
    {
        return $this->result?->scrollId();
    }

    /**
     * @return array<array-key, object>
     */
    public function all(): array
    {
        return $this->items;
    }

    public function first(): ?object
    {
        if ($this->items === []) {
            return null;
        }

        return $this->items[array_key_first($this->items)];
    }

    public function last(): ?object
    {
        if ($this->items === []) {
            return null;
        }

        return $this->items[array_key_last($this->items)];
    }

    public function isEmpty(): bool
    {
        return $this->items === [];
    }

    public function isNotEmpty(): bool
    {
        return $this->items !== [];
    }

    /**
     * @param callable(object, array-key): object $callback
     */
    public function map(callable $callback): static
    {
        $mapped = [];

        foreach ($this->items as $key => $item) {
            $mapped[$key] = $callback($item, $key);
        }

        return new self($mapped, $this->result);
    }

    /**
     * @param callable(object, array-key): bool|null $callback
     */
    public function filter(?callable $callback = null): static
    {
        if ($callback === null) {
            $callback = static fn (object $item): bool => (bool) $item;
        }

        $filtered = [];

        foreach ($this->items as $key => $item) {
            if ($callback($item, $key)) {
                $filtered[$key] = $item;
            }
        }

        return new self($filtered, $this->result);
    }

    /**
     * @param callable(object, array-key): mixed $callback
     */
    public function each(callable $callback): static
    {
        foreach ($this->items as $key => $item) {
            if ($callback($item, $key) === false) {
                break;
            }
        }

        return $this;
    }

    /**
     * @return array<array-key, mixed>
     */
    public function pluck(string $value, ?string $key = null): array
    {
        $results = [];

        foreach ($this->items as $item) {
            $itemValue = $this->valueOf($item, $value);

            if ($key === null) {
                $results[] = $itemValue;

                continue;
            }

            $results[$this->valueOf($item, $key)] = $itemValue;
        }

        return $results;
    }

    /**
     * @param array<array-key, string>|string $keys
     */
    public function only(array|string $keys): static
    {
        /** @var array<array-key, string> $normalised */
        $normalised = is_array($keys) ? $keys : [$keys];

        /** @var array<array-key, object> $items */
        $items = Arr::only($this->items, $normalised);

        return new self($items, $this->result);
    }

    /**
     * @param array<array-key, string>|string $keys
     */
    public function except(array|string $keys): static
    {
        /** @var array<array-key, string> $normalised */
        $normalised = is_array($keys) ? $keys : [$keys];

        /** @var array<array-key, object> $items */
        $items = Arr::except($this->items, $normalised);

        return new self($items, $this->result);
    }

    public function contains(mixed $value): bool
    {
        if ($value instanceof Closure) {
            foreach ($this->items as $key => $item) {
                if ($value($item, $key)) {
                    return true;
                }
            }

            return false;
        }

        foreach ($this->items as $item) {
            if ($item === $value) {
                return true;
            }

            if (is_object($value) && $item == $value) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param callable(object, object): int|null $callback
     */
    public function sort(?callable $callback = null): static
    {
        $items = $this->items;

        if ($callback instanceof Closure) {
            uasort($items, $callback);
        } else {
            uasort($items, static function (object $left, object $right): int {
                return self::comparable($left) <=> self::comparable($right);
            });
        }

        return new self($items, $this->result);
    }

    public function sortBy(string $key, int $options = SORT_REGULAR, bool $descending = false): static
    {
        $items = $this->items;

        uasort($items, function (object $left, object $right) use ($key, $descending): int {
            $result = $this->valueOf($left, $key) <=> $this->valueOf($right, $key);

            return $descending ? -$result : $result;
        });

        return new self($items, $this->result);
    }

    public function values(): static
    {
        return new self(array_values($this->items), $this->result);
    }

    /**
     * @return array<array-key, mixed>
     */
    public function toArray(): array
    {
        return array_map(
            static fn (object $item): mixed => $item instanceof JsonSerializable
                ? $item->jsonSerialize()
                : get_object_vars($item),
            $this->items,
        );
    }

    public function toJson(int $options = 0): string
    {
        return json_encode($this->toArray(), $options | JSON_THROW_ON_ERROR);
    }

    /**
     * @return array<array-key, mixed>
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    public function count(): int
    {
        return count($this->items);
    }

    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->items);
    }

    public function offsetExists(mixed $offset): bool
    {
        return isset($this->items[$offset]);
    }

    public function offsetGet(mixed $offset): mixed
    {
        return $this->items[$offset] ?? null;
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        if ($value === null) {
            $this->offsetUnset($offset);

            return;
        }

        if (!is_object($value)) {
            throw new InvalidArgumentException('A ModelCollection can only hold objects.');
        }

        if ($offset === null) {
            $this->items[] = $value;

            return;
        }

        $this->items[$offset] = $value;
    }

    public function offsetUnset(mixed $offset): void
    {
        unset($this->items[$offset]);
    }

    /**
     * Read a value from an item, supporting models and plain objects.
     */
    private function valueOf(object $item, string $key): mixed
    {
        if ($item instanceof Model) {
            return $item->getAttribute($key);
        }

        $vars = get_object_vars($item);

        return Arr::get($vars, $key);
    }

    /**
     * @return array<array-key, mixed>
     */
    private static function comparable(object $item): array
    {
        if ($item instanceof JsonSerializable) {
            $value = $item->jsonSerialize();

            return is_array($value) ? $value : ['value' => $value];
        }

        return get_object_vars($item);
    }
}
