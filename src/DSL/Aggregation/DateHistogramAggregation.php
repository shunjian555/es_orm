<?php

declare(strict_types=1);

namespace EsOrm\DSL\Aggregation;

/**
 * Buckets documents by a date field using a fixed calendar interval.
 *
 * @see https://www.elastic.co/guide/en/elasticsearch/reference/current/search-aggregations-bucket-datehistogram-aggregation.html
 */
final class DateHistogramAggregation extends Aggregation
{
    /**
     * @param array<string, mixed> $options
     */
    public function __construct(
        public readonly string $field,
        public readonly string $interval,
        public readonly array $options = [],
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'date_histogram' => array_replace(
                ['field' => $this->field, 'calendar_interval' => $this->interval],
                $this->options,
            ),
        ];
    }
}
