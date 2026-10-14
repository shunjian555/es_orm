<?php

declare(strict_types=1);

namespace EsOrm\DSL\Query;

/**
 * Builds an Elasticsearch `parent_id` query, which returns child documents that
 * belong to a given parent document.
 *
 * @see https://www.elastic.co/guide/en/elasticsearch/reference/current/query-dsl-parent-id-query.html
 */
final class ParentIdQuery extends Query
{
    /**
     * @param array<string, mixed> $options
     */
    public function __construct(
        public readonly string $type,
        public readonly string|int $id,
        public readonly array $options = [],
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'parent_id' => array_replace([
                'type' => $this->type,
                'id' => $this->id,
            ], $this->options),
        ];
    }
}
