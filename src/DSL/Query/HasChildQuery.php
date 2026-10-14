<?php

declare(strict_types=1);

namespace EsOrm\DSL\Query;

/**
 * Builds an Elasticsearch `has_child` query, which matches parent documents
 * that have at least one child document matching the wrapped query.
 *
 * @see https://www.elastic.co/guide/en/elasticsearch/reference/current/query-dsl-has-child-query.html
 */
final class HasChildQuery extends Query
{
    /**
     * @param array<string, mixed> $query
     * @param array<string, mixed> $options
     */
    public function __construct(
        public readonly string $type,
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
            'has_child' => array_replace([
                'type' => $this->type,
                'query' => $this->query,
            ], $this->options),
        ];
    }
}
