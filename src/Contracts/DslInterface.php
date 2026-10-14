<?php

declare(strict_types=1);

namespace EsOrm\Contracts;

use JsonSerializable;

/**
 * Contract for every Elasticsearch DSL value object.
 *
 * A DSL object is a small, immutable fragment of the Elasticsearch Query DSL
 * (a query clause, an aggregation, a sort, ...). The only thing the ORM ever
 * needs from it is its array representation.
 */
interface DslInterface extends JsonSerializable
{
    /**
     * Convert the DSL fragment into the array structure understood by
     * Elasticsearch.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array;
}
