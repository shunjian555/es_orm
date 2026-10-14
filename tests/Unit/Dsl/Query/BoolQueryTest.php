<?php

declare(strict_types=1);

namespace EsOrm\Tests\Unit\Dsl\Query;

use EsOrm\DSL\Filter\RawFilter;
use EsOrm\DSL\Query\BoolQuery;
use EsOrm\DSL\Query\TermQuery;
use PHPUnit\Framework\TestCase;

final class BoolQueryTest extends TestCase
{
    public function test_it_builds_a_bool_query_with_must_and_filter(): void
    {
        $query = new BoolQuery(
            must: [new TermQuery('status', 'active')],
            filter: [new TermQuery('type', 'user')],
        );

        self::assertSame([
            'bool' => [
                'must' => [['term' => ['status' => 'active']]],
                'filter' => [['term' => ['type' => 'user']]],
            ],
        ], $query->toArray());
    }

    public function test_it_builds_a_bool_query_with_must_not(): void
    {
        $query = new BoolQuery(mustNot: [new TermQuery('status', 'deleted')]);

        self::assertSame([
            'bool' => ['must_not' => [['term' => ['status' => 'deleted']]]],
        ], $query->toArray());
    }

    public function test_it_builds_a_bool_query_with_should(): void
    {
        $query = new BoolQuery(should: [
            new TermQuery('a', 1),
            new TermQuery('b', 2),
        ]);

        self::assertSame([
            'bool' => ['should' => [
                ['term' => ['a' => 1]],
                ['term' => ['b' => 2]],
            ]],
        ], $query->toArray());
    }

    public function test_it_sets_the_minimum_should_match_as_an_integer(): void
    {
        $query = new BoolQuery(should: [new TermQuery('a', 1)], minimumShouldMatch: 2);

        self::assertSame([
            'bool' => [
                'should' => [['term' => ['a' => 1]]],
                'minimum_should_match' => 2,
            ],
        ], $query->toArray());
    }

    public function test_it_sets_the_minimum_should_match_as_a_string(): void
    {
        $query = new BoolQuery(minimumShouldMatch: '75%');

        self::assertSame(['bool' => ['minimum_should_match' => '75%']], $query->toArray());
    }

    public function test_it_keeps_the_key_order_of_the_occurrence_types(): void
    {
        $query = new BoolQuery(
            must: [new TermQuery('must', 1)],
            mustNot: [new TermQuery('must_not', 1)],
            should: [new TermQuery('should', 1)],
            filter: [new TermQuery('filter', 1)],
            minimumShouldMatch: 1,
        );

        self::assertSame(
            ['must', 'must_not', 'should', 'filter', 'minimum_should_match'],
            array_keys($query->toArray()['bool']),
        );
    }

    public function test_it_merges_raw_array_and_raw_filter_clauses(): void
    {
        $query = new BoolQuery(
            must: [['term' => ['status' => 'active']]],
            filter: [new RawFilter(['range' => ['age' => ['gte' => 18]]])],
        );

        self::assertSame([
            'bool' => [
                'must' => [['term' => ['status' => 'active']]],
                'filter' => [['range' => ['age' => ['gte' => 18]]]],
            ],
        ], $query->toArray());
    }

    public function test_it_builds_a_nested_bool_query(): void
    {
        $query = new BoolQuery(
            must: [
                new BoolQuery(should: [new TermQuery('status', 'active')]),
            ],
        );

        self::assertSame([
            'bool' => [
                'must' => [
                    ['bool' => ['should' => [['term' => ['status' => 'active']]]]],
                ],
            ],
        ], $query->toArray());
    }

    public function test_it_builds_an_empty_bool_query_as_an_object(): void
    {
        self::assertJsonStringEqualsJsonString('{"bool":{}}', json_encode(new BoolQuery()));
    }

    public function test_it_serialises_to_the_same_array_as_to_array(): void
    {
        $query = new BoolQuery(must: [new TermQuery('status', 'active')]);

        self::assertSame($query->toArray(), $query->jsonSerialize());
    }
}
