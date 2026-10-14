<?php

declare(strict_types=1);

namespace EsOrm\DSL\Aggregation;

/**
 * Computes one or more percentiles over the numeric values of a field.
 *
 * @see https://www.elastic.co/guide/en/elasticsearch/reference/current/search-aggregations-metrics-percentile-aggregation.html
 */
final class PercentilesAggregation extends Aggregation
{
    /**
     * @param array<int, int|float> $percents
     * @param array<string, mixed>  $options
     */
    public function __construct(
        public readonly string $field,
        public readonly array $percents = [],
        public readonly array $options = [],
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $body = ['field' => $this->field];

        if ($this->percents !== []) {
            $body['percents'] = array_values($this->percents);
        }

        return ['percentiles' => array_replace($body, $this->options)];
    }
}
