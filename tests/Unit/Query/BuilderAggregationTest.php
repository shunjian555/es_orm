<?php

declare(strict_types=1);

namespace EsOrm\Tests\Unit\Query;

use EsOrm\DSL\Aggregation\AvgAggregation;
use EsOrm\DSL\Aggregation\TermsAggregation;
use EsOrm\Exceptions\InvalidQueryException;
use EsOrm\Query\AggregationBuilder;
use EsOrm\Tests\Fixtures\User;
use EsOrm\Tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Covers the aggregation shorthands and the aggregate() compiler.
 */
final class BuilderAggregationTest extends TestCase
{
    #[DataProvider('aggregationProvider')]
    public function test_it_registers_an_aggregation_shorthand(string $method, array $arguments, array $expectedBody): void
    {
        // Act
        $dsl = User::query()->{$method}(...$arguments)->toDsl();

        // Assert
        self::assertEquals($expectedBody, $dsl['aggs'][$arguments[0]]);
    }

    /**
     * @return array<string, array{0: string, 1: array<int, mixed>, 2: array<string, mixed>}>
     */
    public static function aggregationProvider(): array
    {
        return [
            'terms' => ['termsAgg', ['agg', 'status'], ['terms' => ['field' => 'status']]],
            'stats' => ['statsAgg', ['agg', 'age'], ['stats' => ['field' => 'age']]],
            'avg' => ['avgAgg', ['agg', 'price'], ['avg' => ['field' => 'price']]],
            'sum' => ['sumAgg', ['agg', 'price'], ['sum' => ['field' => 'price']]],
            'min' => ['minAgg', ['agg', 'price'], ['min' => ['field' => 'price']]],
            'max' => ['maxAgg', ['agg', 'price'], ['max' => ['field' => 'price']]],
            'cardinality' => ['cardinalityAgg', ['agg', 'status'], ['cardinality' => ['field' => 'status']]],
            'date_histogram' => ['dateHistogramAgg', ['agg', 'created_at', 'day'], ['date_histogram' => ['field' => 'created_at', 'calendar_interval' => 'day']]],
            'histogram' => ['histogramAgg', ['agg', 'price', 10], ['histogram' => ['field' => 'price', 'interval' => 10]]],
            'range' => ['rangeAgg', ['agg', 'price', [['to' => 50], ['from' => 50]]], ['range' => ['field' => 'price', 'ranges' => [['to' => 50], ['from' => 50]]]]],
            'date_range' => ['dateRangeAgg', ['agg', 'created_at', [['from' => 'now-1d/d', 'to' => 'now/d']]], ['date_range' => ['field' => 'created_at', 'ranges' => [['from' => 'now-1d/d', 'to' => 'now/d']]]]],
            'filters' => ['filtersAgg', ['agg', ['active' => ['term' => ['status' => 'active']]]], ['filters' => ['filters' => ['active' => ['term' => ['status' => 'active']]]]]],
            'nested' => ['nestedAgg', ['agg', 'comments'], ['nested' => ['path' => 'comments']]],
            'reverse_nested' => ['reverseNestedAgg', ['agg', 'comments'], ['reverse_nested' => ['path' => 'comments']]],
            'top_hits' => ['topHitsAgg', ['agg'], ['top_hits' => ['size' => 3]]],
            'top_hits_sized' => ['topHitsAgg', ['agg', 5], ['top_hits' => ['size' => 5]]],
            'percentiles' => ['percentilesAgg', ['agg', 'price', [1, 99]], ['percentiles' => ['field' => 'price', 'percents' => [1, 99]]]],
            'significant_terms' => ['significantTermsAgg', ['agg', 'status'], ['significant_terms' => ['field' => 'status']]],
        ];
    }

    public function test_reverse_nested_emits_an_empty_object_without_a_path(): void
    {
        // Act
        $dsl = User::query()->reverseNestedAgg('agg')->toDsl();

        // Assert
        self::assertEquals(['reverse_nested' => (object) []], $dsl['aggs']['agg']);
    }

    public function test_it_registers_multiple_aggregations_without_overwriting(): void
    {
        // Act
        $dsl = User::query()->termsAgg('by_status', 'status')->avgAgg('avg_age', 'age')->toDsl();

        // Assert
        self::assertCount(2, $dsl['aggs']);
        self::assertEquals(['terms' => ['field' => 'status']], $dsl['aggs']['by_status']);
        self::assertEquals(['avg' => ['field' => 'age']], $dsl['aggs']['avg_age']);
    }

    public function test_aggregate_with_a_closure_registers_sub_aggregations(): void
    {
        // Act
        $dsl = User::query()->aggregate('by_cat', static function (AggregationBuilder $agg): void {
            $agg->terms('categories', 'category_id');
            $agg->avg('avg_price', 'price');
        })->toDsl();

        // Assert
        self::assertEquals([
            'terms' => ['field' => 'category_id'],
            'aggs' => ['avg_price' => ['avg' => ['field' => 'price']]],
        ], $dsl['aggs']['by_cat']);
    }

    public function test_aggregate_with_a_closure_builds_a_nested_sub_aggregation(): void
    {
        // Act
        $dsl = User::query()->aggregate('outer', static function (AggregationBuilder $agg): void {
            $agg->avg('avg_price', 'price');
            $agg->add('by_status', new TermsAggregation('status'), static function (AggregationBuilder $sub): void {
                $sub->sum('total_price', 'price');
            });
        })->toDsl();

        // Assert
        self::assertEquals([
            'avg' => ['field' => 'price'],
            'aggs' => [
                'by_status' => [
                    'terms' => ['field' => 'status'],
                    'aggs' => ['total_price' => ['sum' => ['field' => 'price']]],
                ],
            ],
        ], $dsl['aggs']['outer']);
    }

    public function test_aggregate_with_an_array_registers_a_raw_body(): void
    {
        // Act
        $dsl = User::query()->aggregate('only_active', ['filter' => ['term' => ['status' => 'active']]])->toDsl();

        // Assert
        self::assertEquals(['filter' => ['term' => ['status' => 'active']]], $dsl['aggs']['only_active']);
    }

    public function test_aggregate_accepts_an_aggregation_object(): void
    {
        // Act
        $dsl = User::query()->aggregate('avg_price', new AvgAggregation('price'))->toDsl();

        // Assert
        self::assertEquals(['avg' => ['field' => 'price']], $dsl['aggs']['avg_price']);
    }

    public function test_shorthand_and_closure_aggregations_coexist(): void
    {
        // Act
        $dsl = User::query()
            ->termsAgg('by_status', 'status')
            ->aggregate('fanout', static function (AggregationBuilder $agg): void {
                $agg->terms('by_age', 'age');
                $agg->sum('sum_price', 'price');
            })
            ->toDsl();

        // Assert
        self::assertCount(2, $dsl['aggs']);
        self::assertEquals(['terms' => ['field' => 'status']], $dsl['aggs']['by_status']);
        self::assertEquals([
            'terms' => ['field' => 'age'],
            'aggs' => ['sum_price' => ['sum' => ['field' => 'price']]],
        ], $dsl['aggs']['fanout']);
    }

    public function test_filter_agg_accepts_a_closure(): void
    {
        // Act
        $dsl = User::query()->filterAgg('active_only', static fn ($query) => $query->term('status', 'active'))->toDsl();

        // Assert
        self::assertEquals([
            'filter' => ['bool' => ['must' => [['term' => ['status' => 'active']]]]],
        ], $dsl['aggs']['active_only']);
    }

    public function test_filter_agg_accepts_a_raw_array(): void
    {
        // Act
        $dsl = User::query()->filterAgg('active_only', ['term' => ['status' => 'active']])->toDsl();

        // Assert
        self::assertEquals(['filter' => ['term' => ['status' => 'active']]], $dsl['aggs']['active_only']);
    }

    public function test_aggs_are_omitted_when_none_are_registered(): void
    {
        // Act
        $dsl = User::query()->toDsl();

        // Assert
        self::assertArrayNotHasKey('aggs', $dsl);
    }

    public function test_aggregate_with_an_empty_closure_is_rejected(): void
    {
        // Assert
        $this->expectException(InvalidQueryException::class);

        // Act
        User::query()->aggregate('empty', static function (): void {
        })->toDsl();
    }
}
