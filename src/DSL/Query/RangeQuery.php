<?php

declare(strict_types=1);

namespace EsOrm\DSL\Query;

/**
 * Builds an Elasticsearch `range` query, which matches documents whose field
 * value falls within the supplied bounds (e.g. `gte`, `lt`).
 *
 * @see https://www.elastic.co/guide/en/elasticsearch/reference/current/query-dsl-range-query.html
 */
final class RangeQuery extends Query
{
    /**
     * @param array<string, mixed> $ranges
     */
    public function __construct(
        public readonly string $field,
        public readonly array $ranges,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'range' => [
                $this->field => $this->ranges,
            ],
        ];
    }
}
