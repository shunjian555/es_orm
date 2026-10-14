<?php

declare(strict_types=1);

namespace EsOrm\DSL\Query;

/**
 * Builds an Elasticsearch `ids` query, which matches documents by their
 * document `_id`.
 *
 * @see https://www.elastic.co/guide/en/elasticsearch/reference/current/query-dsl-ids-query.html
 */
final class IdsQuery extends Query
{
    /**
     * @param array<array-key, mixed> $ids
     */
    public function __construct(
        public readonly array $ids,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'ids' => [
                'values' => array_values($this->ids),
            ],
        ];
    }
}
