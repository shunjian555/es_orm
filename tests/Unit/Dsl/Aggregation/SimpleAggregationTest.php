<?php

declare(strict_types=1);

namespace EsOrm\Tests\Unit\Dsl\Aggregation;

use EsOrm\Contracts\AggregationInterface;
use EsOrm\DSL\Aggregation\AvgAggregation;
use EsOrm\DSL\Aggregation\CardinalityAggregation;
use EsOrm\DSL\Aggregation\FilterAggregation;
use EsOrm\DSL\Aggregation\FiltersAggregation;
use EsOrm\DSL\Aggregation\MaxAggregation;
use EsOrm\DSL\Aggregation\MinAggregation;
use EsOrm\DSL\Aggregation\NestedAggregation;
use EsOrm\DSL\Aggregation\RawAggregation;
use EsOrm\DSL\Aggregation\SignificantTermsAggregation;
use EsOrm\DSL\Aggregation\StatsAggregation;
use EsOrm\DSL\Aggregation\SumAggregation;
use EsOrm\DSL\Aggregation\TermsAggregation;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class SimpleAggregationTest extends TestCase
{
    /**
     * @return iterable<string, array{AggregationInterface, array<string, mixed>}>
     */
    public static function simpleAggregationProvider(): iterable
    {
        yield 'terms default' => [
            new TermsAggregation('category'),
            ['terms' => ['field' => 'category']],
        ];
        yield 'terms with size and order' => [
            new TermsAggregation('category', ['size' => 10, 'order' => ['_count' => 'desc']]),
            ['terms' => ['field' => 'category', 'size' => 10, 'order' => ['_count' => 'desc']]],
        ];
        yield 'stats' => [
            new StatsAggregation('price'),
            ['stats' => ['field' => 'price']],
        ];
        yield 'stats with missing' => [
            new StatsAggregation('price', ['missing' => 0]),
            ['stats' => ['field' => 'price', 'missing' => 0]],
        ];
        yield 'avg' => [
            new AvgAggregation('price'),
            ['avg' => ['field' => 'price']],
        ];
        yield 'sum' => [
            new SumAggregation('quantity'),
            ['sum' => ['field' => 'quantity']],
        ];
        yield 'min' => [
            new MinAggregation('price'),
            ['min' => ['field' => 'price']],
        ];
        yield 'max' => [
            new MaxAggregation('price'),
            ['max' => ['field' => 'price']],
        ];
        yield 'cardinality' => [
            new CardinalityAggregation('user_id'),
            ['cardinality' => ['field' => 'user_id']],
        ];
        yield 'cardinality with precision_threshold' => [
            new CardinalityAggregation('user_id', ['precision_threshold' => 40000]),
            ['cardinality' => ['field' => 'user_id', 'precision_threshold' => 40000]],
        ];
        yield 'significant_terms' => [
            new SignificantTermsAggregation('tags'),
            ['significant_terms' => ['field' => 'tags']],
        ];
        yield 'significant_terms with size' => [
            new SignificantTermsAggregation('tags', ['size' => 5]),
            ['significant_terms' => ['field' => 'tags', 'size' => 5]],
        ];
        yield 'nested' => [
            new NestedAggregation('comments'),
            ['nested' => ['path' => 'comments']],
        ];
        yield 'nested with score_mode' => [
            new NestedAggregation('comments', ['score_mode' => 'avg']),
            ['nested' => ['path' => 'comments', 'score_mode' => 'avg']],
        ];
        yield 'filter' => [
            new FilterAggregation(['term' => ['status' => 'active']]),
            ['filter' => ['term' => ['status' => 'active']]],
        ];
        yield 'filters' => [
            new FiltersAggregation([
                'errors' => ['match' => ['level' => 'ERROR']],
                'warnings' => ['match' => ['level' => 'WARNING']],
            ]),
            ['filters' => ['filters' => [
                'errors' => ['match' => ['level' => 'ERROR']],
                'warnings' => ['match' => ['level' => 'WARNING']],
            ]]],
        ];
        yield 'filters with other_bucket option' => [
            new FiltersAggregation(['errors' => ['match' => ['level' => 'ERROR']]], ['other_bucket' => true]),
            ['filters' => ['filters' => ['errors' => ['match' => ['level' => 'ERROR']]], 'other_bucket' => true]],
        ];
        yield 'raw' => [
            new RawAggregation(['scripted_metric' => ['init_script' => 'state.x = 0']]),
            ['scripted_metric' => ['init_script' => 'state.x = 0']],
        ];
        yield 'nested with unicode path' => [
            new NestedAggregation('评论'),
            ['nested' => ['path' => '评论']],
        ];
    }

    /**
     * @param array<string, mixed> $expected
     */
    #[DataProvider('simpleAggregationProvider')]
    public function test_it_builds_the_expected_aggregation_body(AggregationInterface $aggregation, array $expected): void
    {
        self::assertSame($expected, $aggregation->toArray());
    }

    public function test_an_aggregation_serialises_to_the_same_array_as_to_array(): void
    {
        $aggregation = new TermsAggregation('category', ['size' => 5]);

        self::assertSame($aggregation->toArray(), $aggregation->jsonSerialize());
    }

    public function test_a_filter_aggregation_with_an_empty_query_keeps_an_empty_array(): void
    {
        self::assertSame(['filter' => []], (new FilterAggregation([]))->toArray());
    }
}
