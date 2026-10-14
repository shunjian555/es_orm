<?php

declare(strict_types=1);

namespace EsOrm\Tests\Unit\Dsl\Aggregation;

use EsOrm\DSL\Aggregation\DateHistogramAggregation;
use EsOrm\DSL\Aggregation\DateRangeAggregation;
use EsOrm\DSL\Aggregation\HistogramAggregation;
use EsOrm\DSL\Aggregation\PercentilesAggregation;
use EsOrm\DSL\Aggregation\RangeAggregation;
use EsOrm\DSL\Aggregation\ReverseNestedAggregation;
use EsOrm\DSL\Aggregation\TopHitsAggregation;
use PHPUnit\Framework\TestCase;

final class BucketAggregationTest extends TestCase
{
    public function test_it_builds_a_date_histogram_aggregation(): void
    {
        $aggregation = new DateHistogramAggregation('created_at', 'day');

        self::assertSame([
            'date_histogram' => ['field' => 'created_at', 'calendar_interval' => 'day'],
        ], $aggregation->toArray());
    }

    public function test_it_merges_date_histogram_options(): void
    {
        $aggregation = new DateHistogramAggregation('created_at', 'day', [
            'time_zone' => '+08:00',
            'min_doc_count' => 0,
        ]);

        self::assertSame([
            'date_histogram' => [
                'field' => 'created_at',
                'calendar_interval' => 'day',
                'time_zone' => '+08:00',
                'min_doc_count' => 0,
            ],
        ], $aggregation->toArray());
    }

    public function test_it_builds_a_histogram_aggregation_with_an_integer_interval(): void
    {
        $aggregation = new HistogramAggregation('price', 50);

        self::assertSame([
            'histogram' => ['field' => 'price', 'interval' => 50],
        ], $aggregation->toArray());
    }

    public function test_it_builds_a_histogram_aggregation_with_a_float_interval(): void
    {
        $aggregation = new HistogramAggregation('price', 0.5, ['min_doc_count' => 1]);

        self::assertSame([
            'histogram' => ['field' => 'price', 'interval' => 0.5, 'min_doc_count' => 1],
        ], $aggregation->toArray());
    }

    public function test_it_builds_a_range_aggregation(): void
    {
        $ranges = [['to' => 100], ['from' => 100, 'to' => 200]];
        $aggregation = new RangeAggregation('price', $ranges);

        self::assertSame(['range' => ['field' => 'price', 'ranges' => $ranges]], $aggregation->toArray());
    }

    public function test_it_merges_range_aggregation_options(): void
    {
        $aggregation = new RangeAggregation('price', [['to' => 100]], ['keyed' => true]);

        self::assertSame([
            'range' => ['field' => 'price', 'ranges' => [['to' => 100]], 'keyed' => true],
        ], $aggregation->toArray());
    }

    public function test_it_builds_a_date_range_aggregation(): void
    {
        $ranges = [['from' => '2024-01-01', 'to' => '2024-02-01']];
        $aggregation = new DateRangeAggregation('created_at', $ranges, ['format' => 'yyyy-MM-dd']);

        self::assertSame([
            'date_range' => ['field' => 'created_at', 'ranges' => $ranges, 'format' => 'yyyy-MM-dd'],
        ], $aggregation->toArray());
    }

    public function test_it_builds_a_top_hits_aggregation_with_the_default_size(): void
    {
        self::assertSame(['top_hits' => ['size' => 3]], (new TopHitsAggregation())->toArray());
    }

    public function test_it_builds_a_top_hits_aggregation_with_options(): void
    {
        $aggregation = new TopHitsAggregation(10, ['from' => 0, 'sort' => [['price' => 'desc']]]);

        self::assertSame([
            'top_hits' => ['size' => 10, 'from' => 0, 'sort' => [['price' => 'desc']]],
        ], $aggregation->toArray());
    }

    public function test_it_builds_a_percentiles_aggregation_with_default_percents(): void
    {
        self::assertSame([
            'percentiles' => ['field' => 'load_time'],
        ], (new PercentilesAggregation('load_time'))->toArray());
    }

    public function test_it_builds_a_percentiles_aggregation_with_percents(): void
    {
        $aggregation = new PercentilesAggregation('load_time', [95, 99.5]);

        self::assertSame([
            'percentiles' => ['field' => 'load_time', 'percents' => [95, 99.5]],
        ], $aggregation->toArray());
    }

    public function test_it_merges_percentiles_aggregation_options(): void
    {
        $aggregation = new PercentilesAggregation('load_time', [50], ['keyed' => false]);

        self::assertSame([
            'percentiles' => ['field' => 'load_time', 'percents' => [50], 'keyed' => false],
        ], $aggregation->toArray());
    }

    public function test_it_builds_a_reverse_nested_aggregation_without_a_path(): void
    {
        self::assertJsonStringEqualsJsonString('{"reverse_nested":{}}', json_encode(new ReverseNestedAggregation()));
    }

    public function test_it_builds_a_reverse_nested_aggregation_with_a_path(): void
    {
        self::assertSame([
            'reverse_nested' => ['path' => 'user'],
        ], (new ReverseNestedAggregation('user'))->toArray());
    }

    public function test_it_builds_a_reverse_nested_aggregation_with_options_only(): void
    {
        self::assertSame([
            'reverse_nested' => ['size' => 1],
        ], (new ReverseNestedAggregation(options: ['size' => 1]))->toArray());
    }

    public function test_a_bucket_aggregation_serialises_the_same_as_to_array(): void
    {
        $aggregation = new DateHistogramAggregation('created_at', 'month');

        self::assertSame($aggregation->toArray(), $aggregation->jsonSerialize());
    }
}
