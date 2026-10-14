<?php

declare(strict_types=1);

namespace EsOrm\DSL\Query;

/**
 * Builds an Elasticsearch `has_parent` query, which matches child documents
 * whose parent document matches the wrapped query.
 *
 * @see https://www.elastic.co/guide/en/elasticsearch/reference/current/query-dsl-has-parent-query.html
 */
final class HasParentQuery extends Query
{
    /**
     * @param array<string, mixed> $query
     * @param array<string, mixed> $options
     */
    public function __construct(
        public readonly string $parentType,
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
            'has_parent' => array_replace([
                'parent_type' => $this->parentType,
                'query' => $this->query,
            ], $this->options),
        ];
    }
}
