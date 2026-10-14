<?php

declare(strict_types=1);

namespace EsOrm\DSL\Aggregation;

use EsOrm\Contracts\AggregationInterface;

/**
 * Base class for every Elasticsearch aggregation.
 *
 * `toArray()` returns the *body* of the aggregation (for example
 * `['terms' => ['field' => 'category']]`); the aggregation name and the nested
 * sub-aggregations are applied by {@see \EsOrm\Query\AggregationBuilder}.
 */
abstract class Aggregation implements AggregationInterface
{
    /**
     * @return array<string, mixed>
     */
    abstract public function toArray(): array;

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
