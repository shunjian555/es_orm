<?php

declare(strict_types=1);

namespace EsOrm\DSL\Aggregation;

/**
 * Wraps a raw Elasticsearch aggregation body.
 *
 * The supplied array is emitted verbatim, so callers can use any aggregation
 * DSL fragment without it being rewritten by the ORM.
 *
 * @see https://www.elastic.co/guide/en/elasticsearch/reference/current/search-aggregations.html
 */
final class RawAggregation extends Aggregation
{
    /**
     * @param array<string, mixed> $body
     */
    public function __construct(
        public readonly array $body,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return $this->body;
    }
}
