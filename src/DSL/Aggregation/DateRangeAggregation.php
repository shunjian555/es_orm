<?php

declare(strict_types=1);

namespace EsOrm\DSL\Aggregation;

/**
 * Buckets date documents into caller-defined ranges.
 *
 * @see https://www.elastic.co/guide/en/elasticsearch/reference/current/search-aggregations-bucket-daterange-aggregation.html
 */
final class DateRangeAggregation extends Aggregation
{
    /**
     * @param array<int, array<string, mixed>> $ranges
     * @param array<string, mixed>             $options
     */
    public function __construct(
        public readonly string $field,
        public readonly array $ranges,
        public readonly array $options = [],
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'date_range' => array_replace(
                ['field' => $this->field, 'ranges' => $this->ranges],
                $this->options,
            ),
        ];
    }
}
