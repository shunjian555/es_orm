<?php

declare(strict_types=1);

namespace EsOrm\DSL\Aggregation;

/**
 * A single-bucket aggregation that reduces documents to those matching a query.
 *
 * @see https://www.elastic.co/guide/en/elasticsearch/reference/current/search-aggregations-bucket-filter-aggregation.html
 */
final class FilterAggregation extends Aggregation
{
    /**
     * @param array<string, mixed> $query
     */
    public function __construct(
        public readonly array $query,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return ['filter' => $this->query];
    }
}
