<?php

declare(strict_types=1);

namespace EsOrm\Tests\Unit\Query;

use EsOrm\DSL\Query\TermQuery;
use EsOrm\Tests\Fixtures\User;
use EsOrm\Tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Covers the native Elasticsearch query DSL entry points of the Builder.
 */
final class BuilderDslQueryTest extends TestCase
{
    #[DataProvider('queryDslProvider')]
    public function test_it_builds_a_native_query_clause(string $method, array $arguments, array $expected): void
    {
        // Act
        $dsl = User::query()->{$method}(...$arguments)->toDsl();

        // Assert
        self::assertEquals($expected, $dsl['query']['bool']['must'][0]);
    }

    /**
     * @return array<string, array{0: string, 1: array<int, mixed>, 2: array<string, mixed>}>
     */
    public static function queryDslProvider(): array
    {
        return [
            'match' => ['match', ['name', 'Tom'], ['match' => ['name' => ['query' => 'Tom']]]],
            'match_phrase' => ['matchPhrase', ['name', 'Tom Cruise'], ['match_phrase' => ['name' => ['query' => 'Tom Cruise']]]],
            'multi_match' => ['multiMatch', [['name', 'email'], 'Tom'], ['multi_match' => ['query' => 'Tom', 'fields' => ['name', 'email']]]],
            'term' => ['term', ['status', 'active'], ['term' => ['status' => 'active']]],
            'terms' => ['terms', ['status', ['a', 'b']], ['terms' => ['status' => ['a', 'b']]]],
            'range' => ['range', ['age', ['gte' => 18]], ['range' => ['age' => ['gte' => 18]]]],
            'where_exists' => ['whereExists', ['email'], ['exists' => ['field' => 'email']]],
            'prefix' => ['prefix', ['name', 'To'], ['prefix' => ['name' => ['value' => 'To']]]],
            'wildcard' => ['wildcard', ['name', 'T*m'], ['wildcard' => ['name' => ['value' => 'T*m']]]],
            'regexp' => ['regexp', ['name', 'T.*'], ['regexp' => ['name' => ['value' => 'T.*']]]],
            'fuzzy' => ['fuzzy', ['name', 'Tom'], ['fuzzy' => ['name' => ['value' => 'Tom']]]],
            'ids' => ['ids', [[1, 2, 3]], ['ids' => ['values' => [1, 2, 3]]]],
            'query_string' => ['queryString', ['name:Tom'], ['query_string' => ['query' => 'name:Tom']]],
            'simple_query_string' => ['simpleQueryString', ['name:Tom'], ['simple_query_string' => ['query' => 'name:Tom']]],
        ];
    }

    public function test_match_merges_its_options(): void
    {
        // Act
        $dsl = User::query()->match('name', 'Tom', ['operator' => 'and'])->toDsl();

        // Assert
        self::assertEquals(['match' => ['name' => ['query' => 'Tom', 'operator' => 'and']]], $dsl['query']['bool']['must'][0]);
    }

    public function test_nested_wraps_the_closure_query_under_the_path(): void
    {
        // Act
        $dsl = User::query()
            ->nested('items', static fn ($query) => $query->term('items.sku', 'X'), ['score_mode' => 'avg'])
            ->toDsl();

        // Assert
        self::assertEquals([
            'nested' => [
                'path' => 'items',
                'query' => ['bool' => ['must' => [['term' => ['items.sku' => 'X']]]]],
                'score_mode' => 'avg',
            ],
        ], $dsl['query']['bool']['must'][0]);
    }

    public function test_parent_id_builds_the_parent_id_clause(): void
    {
        // Act
        $dsl = User::query()->parentId('user', 42)->toDsl();

        // Assert
        self::assertEquals(['parent_id' => ['type' => 'user', 'id' => 42]], $dsl['query']['bool']['must'][0]);
    }

    public function test_has_child_accepts_a_closure(): void
    {
        // Act
        $dsl = User::query()->hasChild('comment', static fn ($query) => $query->term('approved', true))->toDsl();

        // Assert
        self::assertEquals([
            'has_child' => [
                'type' => 'comment',
                'query' => ['bool' => ['must' => [['term' => ['approved' => true]]]]],
            ],
        ], $dsl['query']['bool']['must'][0]);
    }

    public function test_has_child_accepts_a_raw_array(): void
    {
        // Act
        $dsl = User::query()->hasChild('comment', ['term' => ['approved' => true]])->toDsl();

        // Assert
        self::assertEquals([
            'has_child' => ['type' => 'comment', 'query' => ['term' => ['approved' => true]]],
        ], $dsl['query']['bool']['must'][0]);
    }

    public function test_has_child_accepts_a_dsl_object(): void
    {
        // Act
        $dsl = User::query()->hasChild('comment', new TermQuery('approved', true))->toDsl();

        // Assert
        self::assertEquals([
            'has_child' => ['type' => 'comment', 'query' => ['term' => ['approved' => true]]],
        ], $dsl['query']['bool']['must'][0]);
    }

    public function test_has_parent_builds_the_parent_clause(): void
    {
        // Act
        $dsl = User::query()->hasParent('article', static fn ($query) => $query->term('published', true))->toDsl();

        // Assert
        self::assertEquals([
            'has_parent' => [
                'parent_type' => 'article',
                'query' => ['bool' => ['must' => [['term' => ['published' => true]]]]],
            ],
        ], $dsl['query']['bool']['must'][0]);
    }

    public function test_geo_distance_accepts_an_explicit_location(): void
    {
        // Act
        $dsl = User::query()->geoDistance('location', '10km', [-70.0, 40.0])->toDsl();

        // Assert
        self::assertEquals([
            'geo_distance' => ['distance' => '10km', 'location' => [-70.0, 40.0]],
        ], $dsl['query']['bool']['must'][0]);
    }

    public function test_geo_distance_accepts_the_lat_lon_shorthand_with_a_numeric_distance(): void
    {
        // Act
        $dsl = User::query()->geoDistance('location', 5, 40.0, -70.0)->toDsl();

        // Assert
        self::assertEquals([
            'geo_distance' => ['distance' => '5km', 'location' => ['lat' => 40.0, 'lon' => -70.0]],
        ], $dsl['query']['bool']['must'][0]);
    }

    public function test_geo_bounding_box_builds_the_box_clause(): void
    {
        // Act
        $dsl = User::query()->geoBoundingBox('location', [
            'top_left' => ['lat' => 40.0, 'lon' => -70.0],
            'bottom_right' => ['lat' => 39.0, 'lon' => -69.0],
        ])->toDsl();

        // Assert
        self::assertEquals([
            'geo_bounding_box' => ['location' => [
                'top_left' => ['lat' => 40.0, 'lon' => -70.0],
                'bottom_right' => ['lat' => 39.0, 'lon' => -69.0],
            ]],
        ], $dsl['query']['bool']['must'][0]);
    }

    public function test_geo_polygon_wraps_the_points(): void
    {
        // Act
        $dsl = User::query()->geoPolygon('location', [[-70.0, 40.0], [-69.0, 39.0], [-68.0, 38.0]])->toDsl();

        // Assert
        self::assertEquals([
            'geo_polygon' => ['location' => ['points' => [[-70.0, 40.0], [-69.0, 39.0], [-68.0, 38.0]]]],
        ], $dsl['query']['bool']['must'][0]);
    }

    public function test_bool_wraps_the_nested_bool_query(): void
    {
        // Act
        $dsl = User::query()->bool(static function ($query): void {
            $query->must(['term' => ['a' => 'b']]);
            $query->should(['term' => ['c' => 'd']]);
        })->toDsl();

        // Assert
        self::assertEquals([
            'bool' => ['must' => [
                ['bool' => [
                    'must' => [['term' => ['a' => 'b']]],
                    'should' => [['term' => ['c' => 'd']]],
                ]],
            ]],
        ], $dsl['query']);
    }

    public function test_explicit_bool_occurrences_compile_to_a_flat_bool_query(): void
    {
        // Act
        $dsl = User::query()
            ->must(['term' => ['a' => 'b']])
            ->mustNot(['term' => ['x' => 'y']])
            ->should(['term' => ['c' => 'd']])
            ->filter(['term' => ['e' => 'f']])
            ->minimumShouldMatch(2)
            ->toDsl();

        // Assert
        self::assertEquals([
            'bool' => [
                'must' => [['term' => ['a' => 'b']]],
                'must_not' => [['term' => ['x' => 'y']]],
                'should' => [['term' => ['c' => 'd']]],
                'filter' => [['term' => ['e' => 'f']]],
                'minimum_should_match' => 2,
            ],
        ], $dsl['query']);
    }

    public function test_bool_occurrences_accept_closures(): void
    {
        // Act
        $dsl = User::query()->must(static fn ($query) => $query->term('a', 'b'))->toDsl();

        // Assert
        self::assertEquals([
            'bool' => ['must' => [
                ['bool' => ['must' => [['term' => ['a' => 'b']]]]],
            ]],
        ], $dsl['query']);
    }
}
