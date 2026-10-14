<?php

declare(strict_types=1);

namespace EsOrm\DSL\Query;

/**
 * Builds an Elasticsearch `multi_match` query, which runs a `match` query
 * against several fields at once.
 *
 * @see https://www.elastic.co/guide/en/elasticsearch/reference/current/query-dsl-multi-match-query.html
 */
final class MultiMatchQuery extends Query
{
    /**
     * @param array<array-key, mixed>|string $fields
     * @param array<string, mixed>          $options
     */
    public function __construct(
        public readonly array|string $fields,
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
            'multi_match' => array_replace([
                'query' => $this->query,
                'fields' => $this->fields,
            ], $this->options),
        ];
    }
}
