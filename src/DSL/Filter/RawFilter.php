<?php

declare(strict_types=1);

namespace EsOrm\DSL\Filter;

use EsOrm\Contracts\DslInterface;

/**
 * Wraps a raw Elasticsearch clause for use in filter context (`bool.filter`).
 *
 * The wrapped array is emitted verbatim, so callers can supply any query DSL
 * fragment without it being rewritten by the ORM.
 *
 * @see https://www.elastic.co/guide/en/elasticsearch/reference/current/query-dsl-bool-query.html
 */
final class RawFilter implements DslInterface
{
    /**
     * @param array<string, mixed> $filter
     */
    public function __construct(
        public readonly array $filter,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return $this->filter;
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
