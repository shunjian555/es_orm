<?php

declare(strict_types=1);

namespace EsOrm\DSL\Aggregation;

/**
 * Computes the sum of the numeric values extracted from a field.
 *
 * @see https://www.elastic.co/guide/en/elasticsearch/reference/current/search-aggregations-metrics-sum.html
 */
final class SumAggregation extends Aggregation
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
        return ['sum' => array_replace(['field' => $this->field], $this->options)];
    }
}
