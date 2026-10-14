<?php

declare(strict_types=1);

namespace EsOrm\Tests\Unit\Pagination;

use EsOrm\Model\ModelCollection;
use EsOrm\Query\Pagination\CursorPaginator;
use EsOrm\Query\Pagination\LengthAwarePaginator;
use EsOrm\Query\Pagination\Paginator;
use EsOrm\Tests\Fixtures\User;
use PHPUnit\Framework\TestCase as BaseTestCase;

/**
 * Covers iteration (getIterator) over the three paginator implementations.
 */
final class PaginatorIterationTest extends BaseTestCase
{
    /**
     * @return ModelCollection
     */
    private function items(): ModelCollection
    {
        return new ModelCollection([
            (new User())->setAttribute('name', 'Alice'),
            (new User())->setAttribute('name', 'Bob'),
        ]);
    }

    public function test_a_length_aware_paginator_is_iterable(): void
    {
        // Act
        $paginator = new LengthAwarePaginator($this->items(), 20, 2, 3);

        $names = [];
        foreach ($paginator as $user) {
            $names[] = $user->getAttribute('name');
        }

        // Assert
        self::assertSame(['Alice', 'Bob'], $names);
    }

    public function test_a_simple_paginator_is_iterable(): void
    {
        // Act
        $paginator = new Paginator($this->items(), 2, 1, true);

        $names = [];
        foreach ($paginator as $user) {
            $names[] = $user->getAttribute('name');
        }

        // Assert
        self::assertSame(['Alice', 'Bob'], $names);
    }

    public function test_a_cursor_paginator_is_iterable(): void
    {
        // Act
        $paginator = new CursorPaginator($this->items(), 2, ['1'], true);

        $names = [];
        foreach ($paginator as $user) {
            $names[] = $user->getAttribute('name');
        }

        // Assert
        self::assertSame(['Alice', 'Bob'], $names);
    }
}
