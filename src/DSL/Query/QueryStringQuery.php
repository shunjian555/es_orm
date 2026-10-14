<?php

declare(strict_types=1);

namespace EsOrm\DSL\Query;

/**
 * Builds an Elasticsearch `query_string` query, which parses a rich query
 * expression using the Lucene query syntax.
 *
 * @see https://www.elastic.co/guide/en/elasticsearch/reference/current/query-dsl-query-string-query.html
 */
final class QueryStringQuery extends Query
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
            'query_string' => array_replace(['query' => $this->query], $this->options),
        ];
    }
}
