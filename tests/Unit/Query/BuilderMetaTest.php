<?php

declare(strict_types=1);

namespace EsOrm\Tests\Unit\Query;

use EsOrm\Contracts\QueryBuilderInterface;
use EsOrm\Exceptions\InvalidQueryException;
use EsOrm\Tests\Fixtures\User;
use EsOrm\Tests\TestCase;

/**
 * Covers toDsl()/toJson(), raw merging, dynamic forwarding and boundaries.
 */
final class BuilderMetaTest extends TestCase
{
    public function test_it_implements_the_query_builder_contract(): void
    {
        // Act
        $builder = User::query();

        // Assert
        self::assertInstanceOf(QueryBuilderInterface::class, $builder);
        self::assertInstanceOf(User::class, $builder->getModel());
        self::assertSame('users', $builder->getIndex());
    }

    public function test_from_overrides_the_index(): void
    {
        // Act
        $builder = User::query()->from('users_2024');

        // Assert
        self::assertSame('users_2024', $builder->getIndex());
    }

    public function test_to_dsl_compiles_the_exact_request_body(): void
    {
        // Act
        $dsl = User::query()
            ->where('status', 'active')
            ->orderByDesc('created_at')
            ->select(['id', 'name'])
            ->limit(5)
            ->toDsl();

        // Assert
        self::assertEquals([
            'query' => ['bool' => ['must' => [['term' => ['status' => 'active']]]]],
            'sort' => [['created_at' => 'desc']],
            '_source' => ['includes' => ['id', 'name']],
            'size' => 5,
            'track_total_hits' => true,
        ], $dsl);
    }

    public function test_an_empty_query_compiles_to_match_all(): void
    {
        // Act
        $dsl = User::query()->toDsl();

        // Assert
        self::assertEquals(['match_all' => (object) []], $dsl['query']);
        self::assertArrayNotHasKey('sort', $dsl);
        self::assertArrayNotHasKey('aggs', $dsl);
    }

    public function test_to_json_encodes_the_compiled_body(): void
    {
        // Act
        $json = User::query()->term('status', 'active')->limit(10)->toJson();

        // Assert
        self::assertJsonStringEqualsJsonString(
            '{"query":{"bool":{"must":[{"term":{"status":"active"}}]}},"size":10,"track_total_hits":true}',
            $json,
        );
    }

    public function test_raw_merges_and_overrides_request_body_keys(): void
    {
        // Act
        $dsl = User::query()->where('a', 'b')->raw(['size' => 3, 'min_score' => 1.5])->toDsl();

        // Assert
        self::assertEquals([
            'query' => ['bool' => ['must' => [['term' => ['a' => 'b']]]]],
            'size' => 3,
            'track_total_hits' => true,
            'min_score' => 1.5,
        ], $dsl);
    }

    public function test_raw_query_fragment_is_added_to_the_must_clauses(): void
    {
        // Act
        $dsl = User::query()->rawQuery(['term' => ['x' => 'y']])->toDsl();

        // Assert
        self::assertEquals(['bool' => ['must' => [['term' => ['x' => 'y']]]]], $dsl['query']);
    }

    public function test_raw_query_and_where_clauses_are_combined(): void
    {
        // Act
        $dsl = User::query()->where('a', 'b')->rawQuery(['term' => ['x' => 'y']])->toDsl();

        // Assert
        self::assertSame(2, count($dsl['query']['bool']['must']));
        self::assertEquals(['term' => ['x' => 'y']], $dsl['query']['bool']['must'][0]);
        self::assertEquals(['term' => ['a' => 'b']], $dsl['query']['bool']['must'][1]);
    }

    public function test_it_forwards_to_a_local_model_scope(): void
    {
        // Act
        $dsl = User::query()->active()->toDsl();

        // Assert
        self::assertEquals(['bool' => ['must' => [['term' => ['status' => 'active']]]]], $dsl['query']);
    }

    public function test_it_forwards_scope_arguments(): void
    {
        // Act
        $dsl = User::query()->olderThan(21)->toDsl();

        // Assert
        self::assertEquals(['bool' => ['must' => [['range' => ['age' => ['gt' => 21]]]]]], $dsl['query']);
    }

    public function test_it_forwards_to_an_aggregation_shorthand(): void
    {
        // Act
        $dsl = User::query()->stats('stats_age', 'age')->toDsl();

        // Assert
        self::assertEquals(['stats_age' => ['stats' => ['field' => 'age']]], $dsl['aggs']);
    }

    public function test_it_throws_for_an_undefined_query_method(): void
    {
        // Assert
        $this->expectException(InvalidQueryException::class);

        // Act
        User::query()->thisMethodDoesNotExist();
    }

    public function test_unicode_values_are_preserved(): void
    {
        // Act
        $dsl = User::query()->term('name', '中文名字')->toDsl();

        // Assert
        self::assertEquals(['bool' => ['must' => [['term' => ['name' => '中文名字']]]]], $dsl['query']);
    }

    public function test_the_builder_is_final_and_fluent(): void
    {
        // Act
        $builder = User::query();

        // Assert
        self::assertSame($builder, $builder->where('a', 'b'));
        self::assertSame($builder, $builder->limit(1));
    }
}
