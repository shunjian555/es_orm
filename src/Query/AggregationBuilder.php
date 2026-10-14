<?php

declare(strict_types=1);

namespace EsOrm\Query;

use EsOrm\Contracts\AggregationInterface;
use EsOrm\DSL\Aggregation\AvgAggregation;
use EsOrm\DSL\Aggregation\CardinalityAggregation;
use EsOrm\DSL\Aggregation\DateHistogramAggregation;
use EsOrm\DSL\Aggregation\DateRangeAggregation;
use EsOrm\DSL\Aggregation\FilterAggregation;
use EsOrm\DSL\Aggregation\FiltersAggregation;
use EsOrm\DSL\Aggregation\HistogramAggregation;
use EsOrm\DSL\Aggregation\MaxAggregation;
use EsOrm\DSL\Aggregation\MinAggregation;
use EsOrm\DSL\Aggregation\NestedAggregation;
use EsOrm\DSL\Aggregation\PercentilesAggregation;
use EsOrm\DSL\Aggregation\RangeAggregation;
use EsOrm\DSL\Aggregation\RawAggregation;
use EsOrm\DSL\Aggregation\ReverseNestedAggregation;
use EsOrm\DSL\Aggregation\SignificantTermsAggregation;
use EsOrm\DSL\Aggregation\StatsAggregation;
use EsOrm\DSL\Aggregation\SumAggregation;
use EsOrm\DSL\Aggregation\TermsAggregation;
use EsOrm\DSL\Aggregation\TopHitsAggregation;
use EsOrm\DSL\Dsl;

/**
 * Collects the named aggregations of a search request, including their
 * sub-aggregations.
 *
 * A single aggregation is described by an {@see AggregationInterface} (or a raw
 * array) whose `toArray()` returns the aggregation *body*, e.g.
 * `['terms' => ['field' => 'category_id']]`. The builder is responsible for the
 * aggregation name and for nesting the sub-aggregations under the `aggs` key.
 */
final class AggregationBuilder
{
    /**
     * @var array<string, array{body: array<string, mixed>, sub: array<string, mixed>}>
     */
    private array $aggregations = [];

    /**
     * Register a named aggregation, optionally with sub-aggregations.
     *
     * @param AggregationInterface|array<array-key, mixed> $aggregation
     * @param (callable(AggregationBuilder): void)|null $callback
     */
    public function add(string $name, AggregationInterface|array $aggregation, ?callable $callback = null): static
    {
        $sub = [];

        if ($callback !== null) {
            $nested = new self();
            $callback($nested);
            $sub = $nested->toArray();
        }

        $this->aggregations[$name] = [
            'body' => Dsl::clause($aggregation),
            'sub' => $sub,
        ];

        return $this;
    }

    public function has(string $name): bool
    {
        return array_key_exists($name, $this->aggregations);
    }

    public function isEmpty(): bool
    {
        return $this->aggregations === [];
    }

    /**
     * @return list<string>
     */
    public function names(): array
    {
        return array_keys($this->aggregations);
    }

    /**
     * The registered aggregations as a list, in registration order.
     *
     * @return list<array{name: string, body: array<string, mixed>, sub: array<string, mixed>}>
     */
    public function entries(): array
    {
        $entries = [];

        foreach ($this->aggregations as $name => $aggregation) {
            $entries[] = [
                'name' => $name,
                'body' => $aggregation['body'],
                'sub' => $aggregation['sub'],
            ];
        }

        return $entries;
    }

    /**
     * The first registered aggregation, or null when none exists.
     *
     * @return array{name: string, body: array<string, mixed>, sub: array<string, mixed>}|null
     */
    public function first(): ?array
    {
        foreach ($this->aggregations as $name => $aggregation) {
            return [
                'name' => $name,
                'body' => $aggregation['body'],
                'sub' => $aggregation['sub'],
            ];
        }

        return null;
    }

    public function forget(string $name): static
    {
        unset($this->aggregations[$name]);

        return $this;
    }

    /**
     * Compile the whole `aggs` node of the request body.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $compiled = [];

        foreach ($this->aggregations as $name => $aggregation) {
            $body = $aggregation['body'];

            if ($aggregation['sub'] !== []) {
                $body['aggs'] = $aggregation['sub'];
            }

            $compiled[$name] = $body;
        }

        return $compiled;
    }

    /**
     * Register a raw aggregation (advanced API).
     *
     * @param array<string, mixed> $body
     */
    public function raw(string $name, array $body): static
    {
        return $this->add($name, new RawAggregation($body));
    }

    /**
     * @param array<string, mixed> $options
     */
    public function terms(string $name, string $field, array $options = []): static
    {
        return $this->add($name, new TermsAggregation($field, $options));
    }

    /**
     * @param array<string, mixed> $options
     */
    public function stats(string $name, string $field, array $options = []): static
    {
        return $this->add($name, new StatsAggregation($field, $options));
    }

    /**
     * @param array<string, mixed> $options
     */
    public function avg(string $name, string $field, array $options = []): static
    {
        return $this->add($name, new AvgAggregation($field, $options));
    }

    /**
     * @param array<string, mixed> $options
     */
    public function sum(string $name, string $field, array $options = []): static
    {
        return $this->add($name, new SumAggregation($field, $options));
    }

    /**
     * @param array<string, mixed> $options
     */
    public function min(string $name, string $field, array $options = []): static
    {
        return $this->add($name, new MinAggregation($field, $options));
    }

    /**
     * @param array<string, mixed> $options
     */
    public function max(string $name, string $field, array $options = []): static
    {
        return $this->add($name, new MaxAggregation($field, $options));
    }

    /**
     * @param array<string, mixed> $options
     */
    public function cardinality(string $name, string $field, array $options = []): static
    {
        return $this->add($name, new CardinalityAggregation($field, $options));
    }

    /**
     * @param array<string, mixed> $options
     */
    public function dateHistogram(string $name, string $field, string $interval, array $options = []): static
    {
        return $this->add($name, new DateHistogramAggregation($field, $interval, $options));
    }

    /**
     * @param array<string, mixed> $options
     */
    public function histogram(string $name, string $field, int|float $interval, array $options = []): static
    {
        return $this->add($name, new HistogramAggregation($field, $interval, $options));
    }

    /**
     * @param array<array-key, mixed> $ranges
     * @param array<string, mixed> $options
     */
    public function range(string $name, string $field, array $ranges, array $options = []): static
    {
        return $this->add($name, new RangeAggregation($field, $ranges, $options));
    }

    /**
     * @param array<array-key, mixed> $ranges
     * @param array<string, mixed> $options
     */
    public function dateRange(string $name, string $field, array $ranges, array $options = []): static
    {
        return $this->add($name, new DateRangeAggregation($field, $ranges, $options));
    }

    /**
     * @param array<array-key, mixed> $filters
     * @param array<string, mixed> $options
     */
    public function filters(string $name, array $filters, array $options = []): static
    {
        return $this->add($name, new FiltersAggregation($filters, $options));
    }

    /**
     * @param array<string, mixed> $options
     */
    public function nested(string $name, string $path, array $options = []): static
    {
        return $this->add($name, new NestedAggregation($path, $options));
    }

    /**
     * @param array<string, mixed> $options
     */
    public function reverseNested(string $name, string $path = '', array $options = []): static
    {
        return $this->add($name, new ReverseNestedAggregation($path, $options));
    }

    /**
     * @param array<string, mixed> $options
     */
    public function topHits(string $name, int $size = 3, array $options = []): static
    {
        return $this->add($name, new TopHitsAggregation($size, $options));
    }

    /**
     * @param array<array-key, float|int> $percents
     * @param array<string, mixed> $options
     */
    public function percentiles(string $name, string $field, array $percents = [], array $options = []): static
    {
        return $this->add($name, new PercentilesAggregation($field, $percents, $options));
    }

    /**
     * @param array<string, mixed> $options
     */
    public function significantTerms(string $name, string $field, array $options = []): static
    {
        return $this->add($name, new SignificantTermsAggregation($field, $options));
    }

    /**
     * @param AggregationInterface|array<array-key, mixed> $query
     */
    public function filter(string $name, AggregationInterface|array $query): static
    {
        if ($query instanceof AggregationInterface) {
            return $this->add($name, $query);
        }

        return $this->add($name, new FilterAggregation($query));
    }
}
