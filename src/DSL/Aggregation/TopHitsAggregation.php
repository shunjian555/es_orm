<?php

declare(strict_types=1);

namespace EsOrm\DSL\Aggregation;

/**
 * Returns the top matching documents per bucket.
 *
 * @see https://www.elastic.co/guide/en/elasticsearch/reference/current/search-aggregations-metrics-top-hits-aggregation.html
 */
final class TopHitsAggregation extends Aggregation
{
    /**
     * @param array<string, mixed> $options
     */
    public function __construct(
        public readonly int $size = 3,
        public readonly array $options = [],
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return ['top_hits' => array_replace(['size' => $this->size], $this->options)];
    }
}
