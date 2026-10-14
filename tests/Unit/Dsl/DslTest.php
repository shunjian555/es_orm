<?php

declare(strict_types=1);

namespace EsOrm\Tests\Unit\Dsl;

use EsOrm\DSL\Dsl;
use EsOrm\DSL\Query\TermQuery;
use PHPUnit\Framework\TestCase;

final class DslTest extends TestCase
{
    public function test_it_normalises_a_dsl_object_into_an_array(): void
    {
        self::assertSame(['term' => ['status' => 'active']], Dsl::clause(new TermQuery('status', 'active')));
    }

    public function test_it_returns_an_array_clause_unchanged(): void
    {
        $clause = ['range' => ['age' => ['gte' => 18]]];

        self::assertSame($clause, Dsl::clause($clause));
    }

    public function test_it_normalises_a_list_of_object_and_array_clauses(): void
    {
        $clauses = Dsl::clauses([
            new TermQuery('status', 'active'),
            ['exists' => ['field' => 'email']],
        ]);

        self::assertSame([
            ['term' => ['status' => 'active']],
            ['exists' => ['field' => 'email']],
        ], $clauses);
    }

    public function test_it_reindexes_a_list_of_clauses_with_sparse_keys(): void
    {
        $clauses = Dsl::clauses([3 => new TermQuery('a', 1), 7 => new TermQuery('b', 2)]);

        self::assertSame([
            ['term' => ['a' => 1]],
            ['term' => ['b' => 2]],
        ], $clauses);
    }

    public function test_it_returns_an_empty_list_for_no_clauses(): void
    {
        self::assertSame([], Dsl::clauses([]));
    }

    public function test_it_keeps_unicode_clauses_verbatim(): void
    {
        self::assertSame(['term' => ['name' => '张三']], Dsl::clause(['term' => ['name' => '张三']]));
    }

    public function test_to_object_when_empty_wraps_an_empty_array_as_an_object(): void
    {
        self::assertJsonStringEqualsJsonString('{}', json_encode(Dsl::toObjectWhenEmpty([])));
    }

    public function test_to_object_when_empty_returns_a_non_empty_array_unchanged(): void
    {
        $value = ['path' => 'comments'];

        self::assertSame($value, Dsl::toObjectWhenEmpty($value));
    }
}
