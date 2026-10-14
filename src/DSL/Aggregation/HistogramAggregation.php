<?php

declare(strict_types=1);

namespace EsOrm\DSL\Aggregation;

/**
 * Buckets numeric documents into intervals of a fixed width.
 *
 * @see https://www.elastic.co/guide/en/elasticsearch/reference/current/search-aggregations-bucket-histogram-aggregation.html
 */
final class HistogramAggregation extends Aggregation
{
    /**
     * @param array<string, mixed> $options
     */
    public function __construct(
        public readonly string $field,
        public readonly int|float $interval,
        public readonly array $options = [],
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'histogram' => array_replace(
                ['field' => $this->field, 'interval' => $this->interval],
                $this->options,
            ),
        ];
    }
}
