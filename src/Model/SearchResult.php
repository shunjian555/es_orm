<?php

declare(strict_types=1);

namespace EsOrm\Model;

use ArrayIterator;
use EsOrm\Contracts\SearchResultInterface;
use EsOrm\Support\Arr;
use IteratorAggregate;
use Traversable;

/**
 * Wraps a raw Elasticsearch search response.
 *
 * The hydrated documents live in {@see ModelCollection} (also available
 * directly from `get()`); everything else Elasticsearch returns — total hits,
 * aggregations, suggesters, scroll/PIT handles, the `search_after` cursor and
 * the raw response — is exposed here.
 *
 * @implements IteratorAggregate<int, object>
 */
final class SearchResult implements SearchResultInterface, IteratorAggregate
{
    /**
     * @param array<string, mixed> $raw
     * @param array<string, mixed> $aggregations
     * @param array<string, mixed> $suggest
     * @param array<int|string, mixed> $searchAfter
     */
    public function __construct(
        public readonly array $raw,
        public readonly ModelCollection $hits,
        public readonly array $aggregations = [],
        public readonly array $suggest = [],
        public readonly int $total = 0,
        public readonly ?string $scrollId = null,
        public readonly ?string $pitId = null,
        public readonly array $searchAfter = [],
        public readonly int $took = 0,
        public readonly bool $timedOut = false,
    ) {
    }

    /**
     * Build a result from the raw Elasticsearch response.
     *
     * @param array<string, mixed> $response
     */
    public static function fromResponse(
        array $response,
        ModelCollection $hits,
        ?string $pitId = null,
    ): self {
        $hitsNode = $response['hits'] ?? [];
        $hitsNode = is_array($hitsNode) ? $hitsNode : [];
        $documents = $hitsNode['hits'] ?? [];
        $documents = is_array($documents) ? $documents : [];

        $total = Arr::get($hitsNode, 'total.value');

        if (!is_int($total)) {
            $total = count($documents);
        }

        $aggregations = $response['aggregations'] ?? [];
        $suggest = $response['suggest'] ?? [];
        $scrollId = $response['_scroll_id'] ?? null;

        return new self(
            raw: $response,
            hits: $hits,
            aggregations: is_array($aggregations) ? $aggregations : [],
            suggest: is_array($suggest) ? $suggest : [],
            total: $total,
            scrollId: is_string($scrollId) ? $scrollId : null,
            pitId: $pitId,
            searchAfter: self::cursorFrom($documents),
            took: is_int($response['took'] ?? null) ? $response['took'] : 0,
            timedOut: (bool) ($response['timed_out'] ?? false),
        );
    }

    public function hits(): ModelCollection
    {
        return $this->hits;
    }

    public function total(): int
    {
        return $this->total;
    }

    /**
     * @return array<string, mixed>
     */
    public function aggregations(): array
    {
        return $this->aggregations;
    }

    public function aggregation(string $name): mixed
    {
        return Arr::get($this->aggregations, $name);
    }

    /**
     * @return array<string, mixed>
     */
    public function suggest(): array
    {
        return $this->suggest;
    }

    public function scrollId(): ?string
    {
        return $this->scrollId;
    }

    public function pitId(): ?string
    {
        return $this->pitId;
    }

    /**
     * @return array<int|string, mixed>
     */
    public function searchAfter(): array
    {
        return $this->searchAfter;
    }

    public function took(): int
    {
        return $this->took;
    }

    public function timedOut(): bool
    {
        return $this->timedOut;
    }

    /**
     * @return array<string, mixed>
     */
    public function raw(): array
    {
        return $this->raw;
    }

    /**
     * @return array<array-key, mixed>
     */
    public function toArray(): array
    {
        return $this->hits->toArray();
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
        return $this->hits->count();
    }

    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->hits->all());
    }

    public function offsetExists(mixed $offset): bool
    {
        return $this->hits->offsetExists($offset);
    }

    public function offsetGet(mixed $offset): mixed
    {
        return $this->hits->offsetGet($offset);
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        $this->hits->offsetSet($offset, $value);
    }

    public function offsetUnset(mixed $offset): void
    {
        $this->hits->offsetUnset($offset);
    }

    /**
     * Extract the `search_after` cursor from the last document of a hit list.
     *
     * Exposed so cursor pagination can derive the cursor from the documents that
     * are actually returned to the caller (and not from a look-ahead document).
     *
     * @param array<array-key, mixed> $documents
     * @return array<int|string, mixed>
     */
    public static function cursorFrom(array $documents): array
    {
        if ($documents === []) {
            return [];
        }

        $last = $documents[array_key_last($documents)];

        if (!is_array($last)) {
            return [];
        }

        $sort = $last['sort'] ?? null;

        if (!is_array($sort)) {
            return [];
        }

        /** @var array<int|string, mixed> $sort */
        return $sort;
    }
}
