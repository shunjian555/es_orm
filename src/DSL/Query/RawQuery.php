<?php

declare(strict_types=1);

namespace EsOrm\DSL\Query;

/**
 * Wraps a pre-built Elasticsearch query array, passing it through unchanged so
 * callers can escape the typed builders when needed.
 *
 * @see https://www.elastic.co/guide/en/elasticsearch/reference/current/query-dsl.html
 */
final class RawQuery extends Query
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
        return $this->query;
    }
}
