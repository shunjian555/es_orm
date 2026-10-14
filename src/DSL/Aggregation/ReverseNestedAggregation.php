<?php

declare(strict_types=1);

namespace EsOrm\DSL\Aggregation;

/**
 * A single-bucket aggregation that rejoins nested documents to their parent.
 *
 * When no path is given an empty object is emitted so that `json_encode`
 * produces `{}` rather than `[]`.
 *
 * @see https://www.elastic.co/guide/en/elasticsearch/reference/current/search-aggregations-bucket-reverse-nested-aggregation.html
 */
final class ReverseNestedAggregation extends Aggregation
{
    /**
     * @param array<string, mixed> $options
     */
    public function __construct(
        public readonly string $path = '',
        public readonly array $options = [],
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'reverse_nested' => $this->path === '' && $this->options === []
                ? (object) []
                : array_replace($this->path === '' ? [] : ['path' => $this->path], $this->options),
        ];
    }
}
