<?php

declare(strict_types=1);

namespace EsOrm\Tests\Unit\Dsl\Query;

use EsOrm\Contracts\DslInterface;
use EsOrm\DSL\Query\ExistsQuery;
use EsOrm\DSL\Query\FuzzyQuery;
use EsOrm\DSL\Query\GeoBoundingBoxQuery;
use EsOrm\DSL\Query\GeoDistanceQuery;
use EsOrm\DSL\Query\GeoPolygonQuery;
use EsOrm\DSL\Query\IdsQuery;
use EsOrm\DSL\Query\MatchPhraseQuery;
use EsOrm\DSL\Query\MatchQuery;
use EsOrm\DSL\Query\MultiMatchQuery;
use EsOrm\DSL\Query\ParentIdQuery;
use EsOrm\DSL\Query\PrefixQuery;
use EsOrm\DSL\Query\QueryStringQuery;
use EsOrm\DSL\Query\RangeQuery;
use EsOrm\DSL\Query\RegexpQuery;
use EsOrm\DSL\Query\SimpleQueryStringQuery;
use EsOrm\DSL\Query\TermQuery;
use EsOrm\DSL\Query\TermsQuery;
use EsOrm\DSL\Query\WildcardQuery;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class SimpleQueryTest extends TestCase
{
    /**
     * @return iterable<string, array{DslInterface, array<string, mixed>}>
     */
    public static function simpleQueryProvider(): iterable
    {
        yield 'term scalar' => [new TermQuery('status', 'active'), ['term' => ['status' => 'active']]];
        yield 'term object form with options' => [
            new TermQuery('status', 'active', ['case_insensitive' => true]),
            ['term' => ['status' => ['value' => 'active', 'case_insensitive' => true]]],
        ];
        yield 'term numeric zero' => [new TermQuery('count', 0), ['term' => ['count' => 0]]];
        yield 'term boolean false' => [new TermQuery('flag', false), ['term' => ['flag' => false]]];
        yield 'term null value' => [new TermQuery('nickname', null), ['term' => ['nickname' => null]]];
        yield 'term unicode value' => [new TermQuery('name', '张三'), ['term' => ['name' => '张三']]];
        yield 'match' => [
            new MatchQuery('title', 'quick brown'),
            ['match' => ['title' => ['query' => 'quick brown']]],
        ];
        yield 'match with operator option' => [
            new MatchQuery('title', 'foo', ['operator' => 'and']),
            ['match' => ['title' => ['query' => 'foo', 'operator' => 'and']]],
        ];
        yield 'match_phrase' => [
            new MatchPhraseQuery('title', 'quick fox'),
            ['match_phrase' => ['title' => ['query' => 'quick fox']]],
        ];
        yield 'match_phrase with slop' => [
            new MatchPhraseQuery('title', 'quick fox', ['slop' => 2]),
            ['match_phrase' => ['title' => ['query' => 'quick fox', 'slop' => 2]]],
        ];
        yield 'multi_match array fields' => [
            new MultiMatchQuery(['title', 'body'], 'foo'),
            ['multi_match' => ['query' => 'foo', 'fields' => ['title', 'body']]],
        ];
        yield 'multi_match string fields with type' => [
            new MultiMatchQuery('title,body', 'foo', ['type' => 'best_fields']),
            ['multi_match' => ['query' => 'foo', 'fields' => 'title,body', 'type' => 'best_fields']],
        ];
        yield 'terms' => [new TermsQuery('tags', ['a', 'b']), ['terms' => ['tags' => ['a', 'b']]]];
        yield 'terms reindexes sparse keys' => [
            new TermsQuery('tags', [2 => 'a', 5 => 'b']),
            ['terms' => ['tags' => ['a', 'b']]],
        ];
        yield 'terms empty' => [new TermsQuery('tags', []), ['terms' => ['tags' => []]]];
        yield 'range' => [
            new RangeQuery('age', ['gte' => 18, 'lt' => 65]),
            ['range' => ['age' => ['gte' => 18, 'lt' => 65]]],
        ];
        yield 'range empty' => [new RangeQuery('age', []), ['range' => ['age' => []]]];
        yield 'exists' => [new ExistsQuery('email'), ['exists' => ['field' => 'email']]];
        yield 'prefix' => [new PrefixQuery('name', 'joh'), ['prefix' => ['name' => ['value' => 'joh']]]];
        yield 'prefix with case_insensitive' => [
            new PrefixQuery('name', 'joh', ['case_insensitive' => true]),
            ['prefix' => ['name' => ['value' => 'joh', 'case_insensitive' => true]]],
        ];
        yield 'wildcard' => [new WildcardQuery('name', 'jo*n'), ['wildcard' => ['name' => ['value' => 'jo*n']]]];
        yield 'wildcard with boost' => [
            new WildcardQuery('name', 'jo*n', ['boost' => 2]),
            ['wildcard' => ['name' => ['value' => 'jo*n', 'boost' => 2]]],
        ];
        yield 'regexp' => [new RegexpQuery('name', 'jo.*n'), ['regexp' => ['name' => ['value' => 'jo.*n']]]];
        yield 'regexp with flags' => [
            new RegexpQuery('name', 'jo.*n', ['flags' => 'ALL']),
            ['regexp' => ['name' => ['value' => 'jo.*n', 'flags' => 'ALL']]],
        ];
        yield 'fuzzy with fuzziness' => [
            new FuzzyQuery('name', 'jon', ['fuzziness' => 2]),
            ['fuzzy' => ['name' => ['value' => 'jon', 'fuzziness' => 2]]],
        ];
        yield 'ids' => [new IdsQuery(['1', '2']), ['ids' => ['values' => ['1', '2']]]];
        yield 'ids reindexes sparse keys' => [new IdsQuery([5 => 'a', 9 => 'b']), ['ids' => ['values' => ['a', 'b']]]];
        yield 'ids empty' => [new IdsQuery([]), ['ids' => ['values' => []]]];
        yield 'query_string' => [
            new QueryStringQuery('foo AND bar'),
            ['query_string' => ['query' => 'foo AND bar']],
        ];
        yield 'query_string with default_field' => [
            new QueryStringQuery('foo', ['default_field' => 'title']),
            ['query_string' => ['query' => 'foo', 'default_field' => 'title']],
        ];
        yield 'simple_query_string' => [
            new SimpleQueryStringQuery('foo + bar'),
            ['simple_query_string' => ['query' => 'foo + bar']],
        ];
        yield 'parent_id with integer id' => [
            new ParentIdQuery('question', 42),
            ['parent_id' => ['type' => 'question', 'id' => 42]],
        ];
        yield 'parent_id with string id and option' => [
            new ParentIdQuery('question', 'abc', ['ignore_unmapped' => true]),
            ['parent_id' => ['type' => 'question', 'id' => 'abc', 'ignore_unmapped' => true]],
        ];
        yield 'geo_distance array location' => [
            new GeoDistanceQuery('location', '10km', ['lat' => 40, 'lon' => -70]),
            ['geo_distance' => ['distance' => '10km', 'location' => ['lat' => 40, 'lon' => -70]]],
        ];
        yield 'geo_distance string location' => [
            new GeoDistanceQuery('location', '10km', '40,-70'),
            ['geo_distance' => ['distance' => '10km', 'location' => '40,-70']],
        ];
        yield 'geo_bounding_box' => [
            new GeoBoundingBoxQuery('location', [
                'top_left' => ['lat' => 40, 'lon' => -70],
                'bottom_right' => ['lat' => 30, 'lon' => -60],
            ]),
            ['geo_bounding_box' => ['location' => [
                'top_left' => ['lat' => 40, 'lon' => -70],
                'bottom_right' => ['lat' => 30, 'lon' => -60],
            ]]],
        ];
        yield 'geo_polygon' => [
            new GeoPolygonQuery('location', [['lat' => 0, 'lon' => 0], ['lat' => 1, 'lon' => 1]]),
            ['geo_polygon' => ['location' => ['points' => [
                ['lat' => 0, 'lon' => 0],
                ['lat' => 1, 'lon' => 1],
            ]]]],
        ];
        yield 'geo_polygon with option' => [
            new GeoPolygonQuery('location', [['lat' => 0, 'lon' => 0]], ['validation_method' => 'strict']),
            ['geo_polygon' => [
                'location' => ['points' => [['lat' => 0, 'lon' => 0]]],
                'validation_method' => 'strict',
            ]],
        ];
    }

    /**
     * @param array<string, mixed> $expected
     */
    #[DataProvider('simpleQueryProvider')]
    public function test_it_builds_the_expected_query_dsl(DslInterface $query, array $expected): void
    {
        self::assertSame($expected, $query->toArray());
    }

    public function test_a_query_serialises_to_the_same_array_as_to_array(): void
    {
        $query = new TermQuery('status', 'active');

        self::assertSame($query->toArray(), $query->jsonSerialize());
    }

    public function test_unicode_is_preserved_in_the_json_representation(): void
    {
        self::assertJsonStringEqualsJsonString(
            '{"match":{"name":{"query":"张三"}}}',
            json_encode(new MatchQuery('name', '张三')),
        );
    }
}
