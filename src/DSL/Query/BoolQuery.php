<?php

declare(strict_types=1);

namespace EsOrm\DSL\Query;

use EsOrm\DSL\Dsl;

/**
 * Builds an Elasticsearch `bool` query, which combines other queries with the
 * `must`, `must_not`, `should` and `filter` occurrence types.
 *
 * @see https://www.elastic.co/guide/en/elasticsearch/reference/current/query-dsl-bool-query.html
 */
final class BoolQuery extends Query
{
    /**
     * @param array<array-key, mixed> $must
     * @param array<array-key, mixed> $mustNot
     * @param array<array-key, mixed> $should
     * @param array<array-key, mixed> $filter
     */
    public function __construct(
        public readonly array $must = [],
        public readonly array $mustNot = [],
        public readonly array $should = [],
        public readonly array $filter = [],
        public readonly int|string|null $minimumShouldMatch = null,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $bool = [];

        if ($this->must !== []) {
            $bool['must'] = Dsl::clauses($this->must);
        }

        if ($this->mustNot !== []) {
            $bool['must_not'] = Dsl::clauses($this->mustNot);
        }

        if ($this->should !== []) {
            $bool['should'] = Dsl::clauses($this->should);
        }

        if ($this->filter !== []) {
            $bool['filter'] = Dsl::clauses($this->filter);
        }

        if ($this->minimumShouldMatch !== null) {
            $bool['minimum_should_match'] = $this->minimumShouldMatch;
        }

        if ($bool === []) {
            return ['bool' => (object) []];
        }

        return ['bool' => $bool];
    }
}
