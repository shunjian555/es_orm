<?php

declare(strict_types=1);

namespace EsOrm\DSL\Query;

/**
 * Builds an Elasticsearch `match_phrase` query, which matches documents whose
 * field contains the query terms as an ordered, adjacent phrase.
 *
 * @see https://www.elastic.co/guide/en/elasticsearch/reference/current/query-dsl-match-query-phrase.html
 */
final class MatchPhraseQuery extends Query
{
    /**
     * @param array<string, mixed> $options
     */
    public function __construct(
        public readonly string $field,
        public readonly string $query,
        public readonly array $options = [],
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'match_phrase' => [
                $this->field => array_replace(['query' => $this->query], $this->options),
            ],
        ];
    }
}
