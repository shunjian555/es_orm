<?php

declare(strict_types=1);

namespace EsOrm\Tests\Unit\Scope;

use EsOrm\Contracts\QueryBuilderInterface;
use EsOrm\Exceptions\InvalidQueryException;
use EsOrm\Scope\Scope;
use EsOrm\Tests\Fixtures\User;
use EsOrm\Tests\TestCase;

final class ScopeTest extends TestCase
{
    private function termScope(string $field, mixed $value): Scope
    {
        return new class ($field, $value) implements Scope {
            public function __construct(private string $field, private mixed $value)
            {
            }

            public function apply(QueryBuilderInterface $builder): void
            {
                $builder->term($this->field, $this->value);
            }
        };
    }

    public function test_a_scope_class_can_be_applied_with_with_scope(): void
    {
        // Act
        $dsl = User::query()->withScope($this->termScope('status', 'active'))->toDsl();

        // Assert
        self::assertSame(
            ['bool' => ['must' => [['term' => ['status' => 'active']]]]],
            $dsl['query'],
        );
    }

    public function test_a_named_local_scope_can_be_applied_with_with_scope(): void
    {
        // Act
        $dsl = User::query()->withScope('active')->toDsl();

        // Assert
        self::assertSame(
            ['bool' => ['must' => [['term' => ['status' => 'active']]]]],
            $dsl['query'],
        );
    }

    public function test_a_local_scope_can_be_called_through_magic_call(): void
    {
        // Act
        $dsl = User::query()->active()->toDsl();

        // Assert
        self::assertSame(
            ['bool' => ['must' => [['term' => ['status' => 'active']]]]],
            $dsl['query'],
        );
    }

    public function test_a_local_scope_can_receive_arguments(): void
    {
        // Act
        $dsl = User::query()->olderThan(18)->toDsl();

        // Assert
        self::assertSame(
            ['bool' => ['must' => [['range' => ['age' => ['gt' => 18]]]]]],
            $dsl['query'],
        );
    }

    public function test_a_global_scope_is_applied_to_every_query(): void
    {
        // Arrange
        User::addGlobalScope($this->termScope('status', 'active'));

        // Act
        $dsl = User::query()->toDsl();

        // Assert
        self::assertSame(
            ['bool' => ['must' => [['term' => ['status' => 'active']]]]],
            $dsl['query'],
        );
    }

    public function test_a_global_scope_is_combined_with_local_constraints(): void
    {
        // Arrange
        User::addGlobalScope($this->termScope('status', 'active'));

        // Act
        $dsl = User::query()->where('age', '>', 18)->toDsl();

        // Assert
        self::assertSame([
            'bool' => [
                'must' => [
                    ['term' => ['status' => 'active']],
                    ['range' => ['age' => ['gt' => 18]]],
                ],
            ],
        ], $dsl['query']);
    }

    public function test_a_global_scope_is_exposed_through_the_model(): void
    {
        // Arrange
        User::addGlobalScope($this->termScope('status', 'active'));

        // Act
        $scopes = (new User())->getGlobalScopes();

        // Assert
        self::assertCount(1, $scopes);
    }

    public function test_an_unknown_named_scope_is_rejected(): void
    {
        // Assert
        $this->expectException(InvalidQueryException::class);
        $this->expectExceptionMessage('Unknown scope [missing].');

        // Act
        User::query()->withScope('missing');
    }
}
