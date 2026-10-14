<?php

declare(strict_types=1);

namespace EsOrm\Tests\Unit\Dsl;

use EsOrm\DSL\Aggregation\RawAggregation;
use EsOrm\DSL\Filter\RawFilter;
use EsOrm\DSL\Query\BoolQuery;
use EsOrm\DSL\Query\RawQuery;
use PHPUnit\Framework\TestCase;

final class RawDslTest extends TestCase
{
    public function test_raw_query_forwards_the_array_unchanged(): void
    {
        $raw = [
            'function_score' => [
                'query' => ['match_all' => (object) []],
                'boost' => '5',
            ],
        ];

        self::assertSame($raw, (new RawQuery($raw))->toArray());
    }

    public function test_raw_query_keeps_unicode_verbatim(): void
    {
        $raw = ['term' => ['name' => '张三']];

        self::assertSame($raw, (new RawQuery($raw))->toArray());
    }

    public function test_raw_query_returns_the_same_array_on_repeated_calls(): void
    {
        $query = new RawQuery(['ids' => ['values' => ['a', 'b']]]);

        self::assertSame($query->toArray(), $query->toArray());
        self::assertSame($query->toArray(), $query->jsonSerialize());
    }

    public function test_raw_query_and_raw_filter_can_be_mixed_inside_a_bool_query(): void
    {
        $query = new BoolQuery(
            must: [new RawQuery(['term' => ['status' => 'active']])],
            filter: [new RawFilter(['range' => ['age' => ['gte' => 18]]])],
        );

        self::assertSame([
            'bool' => [
                'must' => [['term' => ['status' => 'active']]],
                'filter' => [['range' => ['age' => ['gte' => 18]]]],
            ],
        ], $query->toArray());
    }

    public function test_raw_filter_forwards_the_array_unchanged(): void
    {
        $raw = ['geo_bounding_box' => ['location' => [
            'top_left' => ['lat' => 40, 'lon' => -70],
            'bottom_right' => ['lat' => 30, 'lon' => -60],
        ]]];

        self::assertSame($raw, (new RawFilter($raw))->toArray());
        self::assertSame($raw, (new RawFilter($raw))->jsonSerialize());
    }

    public function test_raw_aggregation_forwards_the_body_unchanged(): void
    {
        $body = [
            'scripted_metric' => [
                'init_script' => 'state.transactions = []',
                'map_script' => 'state.transactions.add(doc.type.value == "sale" ? doc.price.value : 0)',
            ],
        ];

        self::assertSame($body, (new RawAggregation($body))->toArray());
    }

    public function test_raw_fragments_are_not_rewritten_by_the_dsl_helper(): void
    {
        $raw = ['match_none' => []];
        $filter = ['exists' => ['field' => 'email']];

        self::assertSame($raw, \EsOrm\DSL\Dsl::clause(new RawQuery($raw)));
        self::assertSame($filter, \EsOrm\DSL\Dsl::clause(new RawFilter($filter)));
    }
}
