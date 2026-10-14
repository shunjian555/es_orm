<?php

declare(strict_types=1);

namespace EsOrm\DSL\Aggregation;

/**
 * A single-bucket aggregation that joins documents nested under a given path.
 *
 * @see https://www.elastic.co/guide/en/elasticsearch/reference/current/search-aggregations-bucket-nested-aggregation.html
 */
final class NestedAggregation extends Aggregation
{
    /**
     * @param array<string, mixed> $options
     */
    public function __construct(
        public readonly string $path,
        public readonly array $options = [],
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return ['nested' => array_replace(['path' => $this->path], $this->options)];
    }
}
