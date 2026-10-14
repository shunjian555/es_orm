<?php

declare(strict_types=1);

namespace EsOrm\DSL\Query;

/**
 * Builds an Elasticsearch `nested` query, which runs a query against nested
 * documents and keeps matching documents independently.
 *
 * @see https://www.elastic.co/guide/en/elasticsearch/reference/current/query-dsl-nested-query.html
 */
final class NestedQuery extends Query
{
    /**
     * @param array<string, mixed> $query
     * @param array<string, mixed> $options
     */
    public function __construct(
        public readonly string $path,
        public readonly array $query,
        public readonly array $options = [],
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'nested' => array_replace([
                'path' => $this->path,
                'query' => $this->query,
            ], $this->options),
        ];
    }
}
