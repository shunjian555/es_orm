<?php

declare(strict_types=1);

namespace EsOrm\DSL\Aggregation;

/**
 * Computes a multi-value statistics summary (min, max, avg, sum, count) over a field.
 *
 * @see https://www.elastic.co/guide/en/elasticsearch/reference/current/search-aggregations-metrics-stats.html
 */
final class StatsAggregation extends Aggregation
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
        return ['stats' => array_replace(['field' => $this->field], $this->options)];
    }
}
