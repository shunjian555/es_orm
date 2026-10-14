<?php

declare(strict_types=1);

namespace EsOrm\DSL\Aggregation;

/**
 * Returns interesting or unusual occurrences of terms in a set of documents.
 *
 * @see https://www.elastic.co/guide/en/elasticsearch/reference/current/search-aggregations-bucket-significantterms-aggregation.html
 */
final class SignificantTermsAggregation extends Aggregation
{
    /**
     * @param array<string, mixed> $options
     */
    public function __construct(
        public readonly string $field,
        public readonly array $options = [],
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return ['significant_terms' => array_replace(['field' => $this->field], $this->options)];
    }
}
