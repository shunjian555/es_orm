<?php

declare(strict_types=1);

namespace EsOrm\DSL\Aggregation;

/**
 * Approximates the number of distinct values of a field.
 *
 * @see https://www.elastic.co/guide/en/elasticsearch/reference/current/search-aggregations-metrics-cardinality.html
 */
final class CardinalityAggregation extends Aggregation
{
    /**
     * @param array<string, mixed> $options
     */
    public function __construct(
        public readonly string $field,
        public readonly array $options = [],
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return ['cardinality' => array_replace(['field' => $this->field], $this->options)];
    }
}
