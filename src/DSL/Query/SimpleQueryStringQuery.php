<?php

declare(strict_types=1);

namespace EsOrm\DSL\Query;

/**
 * Builds an Elasticsearch `simple_query_string` query, a fault-tolerant variant
 * of the query string query that never throws on malformed syntax.
 *
 * @see https://www.elastic.co/guide/en/elasticsearch/reference/current/query-dsl-simple-query-string-query.html
 */
final class SimpleQueryStringQuery extends Query
{
    /**
     * @param array<string, mixed> $options
     */
    public function __construct(
        public readonly string $query,
        public readonly array $options = [],
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'simple_query_string' => array_replace(['query' => $this->query], $this->options),
        ];
    }
}
