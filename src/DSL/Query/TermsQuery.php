<?php

declare(strict_types=1);

namespace EsOrm\DSL\Query;

/**
 * Builds an Elasticsearch `terms` query, which matches documents that contain
 * any of the supplied exact terms in a given field.
 *
 * @see https://www.elastic.co/guide/en/elasticsearch/reference/current/query-dsl-terms-query.html
 */
final class TermsQuery extends Query
{
    /**
     * @param array<array-key, mixed> $values
     */
    public function __construct(
        public readonly string $field,
        public readonly array $values,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'terms' => [
                $this->field => array_values($this->values),
            ],
        ];
    }
}
