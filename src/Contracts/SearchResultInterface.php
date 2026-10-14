<?php

declare(strict_types=1);

namespace EsOrm\Contracts;

use ArrayAccess;
use Countable;
use IteratorAggregate;
use JsonSerializable;

/**
 * The result of a search request: the hydrated hits plus all the Elasticsearch
 * specific metadata a plain collection cannot carry (total, aggregations,
 * suggesters, scroll id, PIT id, search_after cursor).
 *
 * @extends ArrayAccess<int, ModelInterface>
 * @extends IteratorAggregate<int, ModelInterface>
 */
interface SearchResultInterface extends ArrayAccess, Countable, IteratorAggregate, JsonSerializable
{
    /**
     * The hydrated documents.
     *
     * @return CollectionInterface<array-key, object>
     */
    public function hits(): CollectionInterface;

    /**
     * Total number of matching documents.
     */
    public function total(): int;

    /**
     * @return array<string, mixed>
     */
    public function aggregations(): array;

    /**
     * A single aggregation result by name.
     */
    public function aggregation(string $name): mixed;

    /**
     * @return array<string, mixed>
     */
    public function suggest(): array;

    /**
     * The scroll id when the request used `scroll`.
     */
    public function scrollId(): ?string;

    /**
     * The Point In Time id when the request used PIT.
     */
    public function pitId(): ?string;

    /**
     * The sort values of the last hit, usable as the next `search_after` cursor.
     *
     * @return array<int|string, mixed>
     */
    public function searchAfter(): array;

    /**
     * Execution time as reported by Elasticsearch, in milliseconds.
     */
    public function took(): int;

    /**
     * Whether Elasticsearch reported a timeout.
     */
    public function timedOut(): bool;

    /**
     * The raw Elasticsearch response.
     *
     * @return array<string, mixed>
     */
    public function raw(): array;
}
