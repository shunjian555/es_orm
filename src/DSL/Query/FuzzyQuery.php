<?php

declare(strict_types=1);

namespace EsOrm\DSL\Query;

/**
 * Builds an Elasticsearch `fuzzy` query, which matches documents whose field
 * contains terms within a specified edit distance of the query value.
 *
 * @see https://www.elastic.co/guide/en/elasticsearch/reference/current/query-dsl-fuzzy-query.html
 */
final class FuzzyQuery extends Query
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
            'fuzzy' => [
                $this->field => array_replace(['value' => $this->value], $this->options),
            ],
        ];
    }
}
