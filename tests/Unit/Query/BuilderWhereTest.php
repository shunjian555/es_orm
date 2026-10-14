<?php

declare(strict_types=1);

namespace EsOrm\Tests\Unit\Query;

use EsOrm\Exceptions\InvalidQueryException;
use EsOrm\Tests\Fixtures\User;
use EsOrm\Tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Covers the Eloquent-like where() surface of the Query Builder.
 */
final class BuilderWhereTest extends TestCase
{
    #[DataProvider('operatorProvider')]
    public function test_it_translates_a_where_operator_to_dsl(string $operator, mixed $value, array $expected): void
    {
        // Act
        $dsl = User::query()->where('field', $operator, $value)->toDsl();

        // Assert
        self::assertEquals($expected, $dsl['query']['bool']['must'][0]);
    }

    /**
     * @return array<string, array{0: string, 1: mixed, 2: array<string, mixed>}>
     */
    public static function operatorProvider(): array
    {
        return [
            'equals' => ['=', 'active', ['term' => ['field' => 'active']]],
            'double equals' => ['==', 'active', ['term' => ['field' => 'active']]],
            'not equals' => ['!=', 'active', ['bool' => ['must_not' => [['term' => ['field' => 'active']]]]]],
            'angle not equals' => ['<>', 'active', ['bool' => ['must_not' => [['term' => ['field' => 'active']]]]]],
            'greater than' => ['>', 18, ['range' => ['field' => ['gt' => 18]]]],
            'greater or equal' => ['>=', 18, ['range' => ['field' => ['gte' => 18]]]],
            'less than' => ['<', 18, ['range' => ['field' => ['lt' => 18]]]],
            'less or equal' => ['<=', 18, ['range' => ['field' => ['lte' => 18]]]],
            'like' => ['like', 'Tom%', ['wildcard' => ['field' => ['value' => 'Tom*']]]],
            'not like' => ['not like', 'Tom%', ['bool' => ['must_not' => [['wildcard' => ['field' => ['value' => 'Tom*']]]]]]],
            'in' => ['in', [1, 2, 3], ['terms' => ['field' => [1, 2, 3]]]],
            'not in' => ['not in', [1, 2, 3], ['bool' => ['must_not' => [['terms' => ['field' => [1, 2, 3]]]]]]],
        ];
    }

    public function test_it_defaults_to_the_equals_operator_with_two_arguments(): void
    {
        // Act
        $dsl = User::query()->where('status', 'active')->toDsl();

        // Assert
        self::assertEquals(['bool' => ['must' => [['term' => ['status' => 'active']]]]], $dsl['query']);
    }

    public function test_it_accepts_an_array_of_conditions(): void
    {
        // Act
        $dsl = User::query()->where(['status' => 'active', 'age' => 18])->toDsl();

        // Assert
        self::assertEquals([
            'bool' => ['must' => [
                ['term' => ['status' => 'active']],
                ['term' => ['age' => 18]],
            ]],
        ], $dsl['query']);
    }

    public function test_it_accepts_a_closure_condition(): void
    {
        // Act
        $dsl = User::query()->where(static fn ($query) => $query->term('status', 'active'))->toDsl();

        // Assert
        self::assertEquals([
            'bool' => ['must' => [
                ['bool' => ['must' => [['term' => ['status' => 'active']]]]],
            ]],
        ], $dsl['query']);
    }

    public function test_it_compiles_nested_closures(): void
    {
        // Act
        $dsl = User::query()->where(static function ($query): void {
            $query->where(static fn ($nested) => $nested->term('a', 1));
        })->toDsl();

        // Assert
        self::assertEquals([
            'bool' => ['must' => [
                ['bool' => ['must' => [
                    ['bool' => ['must' => [['term' => ['a' => 1]]]]],
                ]]],
            ]],
        ], $dsl['query']);
    }

    public function test_or_where_starts_a_new_group_with_or_semantics(): void
    {
        // Act
        $dsl = User::query()->where('a', 'x')->orWhere('b', 'y')->toDsl();

        // Assert
        self::assertEquals([
            'bool' => [
                'should' => [
                    ['bool' => ['must' => [['term' => ['a' => 'x']]]]],
                    ['bool' => ['must' => [['term' => ['b' => 'y']]]]],
                ],
                'minimum_should_match' => 1,
            ],
        ], $dsl['query']);
    }

    public function test_three_or_groups_produce_three_should_clauses(): void
    {
        // Act
        $dsl = User::query()->where('a', 1)->orWhere('b', 2)->orWhere('c', 3)->toDsl();

        // Assert
        self::assertCount(3, $dsl['query']['bool']['should']);
        self::assertSame(1, $dsl['query']['bool']['minimum_should_match']);
    }

    public function test_where_in_builds_a_terms_clause(): void
    {
        // Act
        $dsl = User::query()->whereIn('id', [1, 2, 3])->toDsl();

        // Assert
        self::assertEquals(['bool' => ['must' => [['terms' => ['id' => [1, 2, 3]]]]]], $dsl['query']);
    }

    public function test_where_not_in_builds_a_must_not_terms_clause(): void
    {
        // Act
        $dsl = User::query()->whereNotIn('id', [1, 2, 3])->toDsl();

        // Assert
        self::assertEquals(['bool' => ['must_not' => [['terms' => ['id' => [1, 2, 3]]]]]], $dsl['query']);
    }

    public function test_where_null_builds_a_must_not_exists_clause(): void
    {
        // Act
        $dsl = User::query()->whereNull('deleted_at')->toDsl();

        // Assert
        self::assertEquals(['bool' => ['must_not' => [['exists' => ['field' => 'deleted_at']]]]], $dsl['query']);
    }

    public function test_where_not_null_builds_a_must_exists_clause(): void
    {
        // Act
        $dsl = User::query()->whereNotNull('email')->toDsl();

        // Assert
        self::assertEquals(['bool' => ['must' => [['exists' => ['field' => 'email']]]]], $dsl['query']);
    }

    public function test_where_between_builds_an_inclusive_range(): void
    {
        // Act
        $dsl = User::query()->whereBetween('age', [18, 30])->toDsl();

        // Assert
        self::assertEquals(['bool' => ['must' => [['range' => ['age' => ['gte' => 18, 'lte' => 30]]]]]], $dsl['query']);
    }

    public function test_where_not_between_builds_a_must_not_range(): void
    {
        // Act
        $dsl = User::query()->whereNotBetween('age', [18, 30])->toDsl();

        // Assert
        self::assertEquals(['bool' => ['must_not' => [['range' => ['age' => ['gte' => 18, 'lte' => 30]]]]]], $dsl['query']);
    }

    public function test_where_exists_builds_an_exists_clause(): void
    {
        // Act
        $dsl = User::query()->whereExists('profile')->toDsl();

        // Assert
        self::assertEquals(['bool' => ['must' => [['exists' => ['field' => 'profile']]]]], $dsl['query']);
    }

    public function test_where_not_exists_builds_a_must_not_exists_clause(): void
    {
        // Act
        $dsl = User::query()->whereNotExists('profile')->toDsl();

        // Assert
        self::assertEquals(['bool' => ['must_not' => [['exists' => ['field' => 'profile']]]]], $dsl['query']);
    }

    public function test_where_nested_wraps_a_closure_in_a_must_clause(): void
    {
        // Act
        $dsl = User::query()->whereNested(static fn ($query) => $query->term('status', 'active'))->toDsl();

        // Assert
        self::assertEquals([
            'bool' => ['must' => [
                ['bool' => ['must' => [['term' => ['status' => 'active']]]]],
            ]],
        ], $dsl['query']);
    }

    public function test_or_where_nested_creates_an_alternative_group(): void
    {
        // Act
        $dsl = User::query()
            ->where('status', 'active')
            ->orWhereNested(static fn ($query) => $query->term('status', 'pending'))
            ->toDsl();

        // Assert
        self::assertCount(2, $dsl['query']['bool']['should']);
        self::assertSame(1, $dsl['query']['bool']['minimum_should_match']);
        // The closure is first compiled into a bool query and then wrapped by the
        // OR group, hence the double bool/must nesting.
        self::assertEquals(
            ['bool' => ['must' => [['bool' => ['must' => [['term' => ['status' => 'pending']]]]]]]],
            $dsl['query']['bool']['should'][1],
        );
    }

    public function test_it_throws_when_the_operator_is_unsupported(): void
    {
        // Assert
        $this->expectException(InvalidQueryException::class);

        // Act
        User::query()->where('age', '~', 18)->toDsl();
    }
}
