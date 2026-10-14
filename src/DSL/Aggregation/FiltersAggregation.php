<?php

declare(strict_types=1);

namespace EsOrm\DSL\Aggregation;

/**
 * Defines a multi-bucket aggregation where each bucket is associated with a filter.
 *
 * @see https://www.elastic.co/guide/en/elasticsearch/reference/current/search-aggregations-bucket-filters-aggregation.html
 */
final class FiltersAggregation extends Aggregation
{
    /**
     * @param array<string, mixed> $filters
     * @param array<string, mixed> $options
     */
    public function __construct(
        public readonly array $filters,
        public readonly array $options = [],
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return ['filters' => array_replace(['filters' => $this->filters], $this->options)];
    }
}
