<?php

declare(strict_types=1);

namespace EsOrm\DSL\Query;

/**
 * Builds an Elasticsearch `term` query, which matches documents that contain an
 * exact, not analysed term in a given field.
 *
 * @see https://www.elastic.co/guide/en/elasticsearch/reference/current/query-dsl-term-query.html
 */
final class TermQuery extends Query
{
    /**
     * @param array<string, mixed> $options
     */
    public function __construct(
        public readonly string $field,
        public readonly mixed $value,
        public readonly array $options = [],
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        if ($this->options === []) {
            return [
                'term' => [
                    $this->field => $this->value,
                ],
            ];
        }

        return [
            'term' => [
                $this->field => array_replace(['value' => $this->value], $this->options),
            ],
        ];
    }
}
