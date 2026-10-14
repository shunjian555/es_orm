<?php

declare(strict_types=1);

namespace EsOrm\DSL\Query;

/**
 * Builds an Elasticsearch `wildcard` query, which matches documents whose field
 * matches a single-term pattern containing `*` and `?` wildcards.
 *
 * @see https://www.elastic.co/guide/en/elasticsearch/reference/current/query-dsl-wildcard-query.html
 */
final class WildcardQuery extends Query
{
    /**
     * @param array<string, mixed> $options
     */
    public function __construct(
        public readonly string $field,
        public readonly string $value,
        public readonly array $options = [],
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'wildcard' => [
                $this->field => array_replace(['value' => $this->value], $this->options),
            ],
        ];
    }
}
