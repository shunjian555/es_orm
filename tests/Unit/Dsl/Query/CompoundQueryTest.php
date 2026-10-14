<?php

declare(strict_types=1);

namespace EsOrm\Tests\Unit\Dsl\Query;

use EsOrm\DSL\Query\HasChildQuery;
use EsOrm\DSL\Query\HasParentQuery;
use EsOrm\DSL\Query\NestedQuery;
use EsOrm\DSL\Query\TermQuery;
use PHPUnit\Framework\TestCase;

final class CompoundQueryTest extends TestCase
{
    public function test_it_builds_a_nested_query(): void
    {
        $query = new NestedQuery('comments', ['term' => ['comments.author' => 'Tom']]);

        self::assertSame([
            'nested' => [
                'path' => 'comments',
                'query' => ['term' => ['comments.author' => 'Tom']],
            ],
        ], $query->toArray());
    }

    public function test_it_merges_nested_query_options(): void
    {
        $query = new NestedQuery(
            'comments',
            ['term' => ['comments.author' => 'Tom']],
            ['score_mode' => 'avg', 'inner_hits' => (object) []],
        );

        self::assertEquals([
            'nested' => [
                'path' => 'comments',
                'query' => ['term' => ['comments.author' => 'Tom']],
                'score_mode' => 'avg',
                'inner_hits' => (object) [],
            ],
        ], $query->toArray());
    }

    public function test_it_wraps_a_nested_bool_query_array(): void
    {
        $nested = ['bool' => ['must' => [['term' => ['comments.status' => 'active']]]]];
        $query = new NestedQuery('comments', $nested);

        self::assertSame(['nested' => ['path' => 'comments', 'query' => $nested]], $query->toArray());
    }

    public function test_it_keeps_an_empty_nested_query_array(): void
    {
        $query = new NestedQuery('comments', []);

        self::assertSame(['nested' => ['path' => 'comments', 'query' => []]], $query->toArray());
    }

    public function test_it_builds_a_has_child_query(): void
    {
        $query = new HasChildQuery('answer', ['match' => ['owner' => 'john']]);

        self::assertSame([
            'has_child' => [
                'type' => 'answer',
                'query' => ['match' => ['owner' => 'john']],
            ],
        ], $query->toArray());
    }

    public function test_it_merges_has_child_options(): void
    {
        $query = new HasChildQuery('answer', ['match' => ['owner' => 'john']], ['score_mode' => 'max']);

        self::assertSame([
            'has_child' => [
                'type' => 'answer',
                'query' => ['match' => ['owner' => 'john']],
                'score_mode' => 'max',
            ],
        ], $query->toArray());
    }

    public function test_it_builds_a_has_parent_query(): void
    {
        $query = new HasParentQuery('blog', ['term' => ['tag' => 'something']]);

        self::assertSame([
            'has_parent' => [
                'parent_type' => 'blog',
                'query' => ['term' => ['tag' => 'something']],
            ],
        ], $query->toArray());
    }

    public function test_it_accepts_a_dsl_object_as_the_compound_query(): void
    {
        $query = new NestedQuery('comments', (new TermQuery('comments.author', 'Tom'))->toArray());

        self::assertSame(
            ['nested' => ['path' => 'comments', 'query' => ['term' => ['comments.author' => 'Tom']]]],
            $query->toArray(),
        );
    }

    public function test_it_serialises_a_compound_query(): void
    {
        $query = new HasParentQuery('blog', ['term' => ['tag' => 'something']]);

        self::assertSame($query->toArray(), $query->jsonSerialize());
    }
}
