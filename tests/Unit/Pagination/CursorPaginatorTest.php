<?php

declare(strict_types=1);

namespace EsOrm\Tests\Unit\Pagination;

use EsOrm\Model\ModelCollection;
use EsOrm\Query\Pagination\CursorPaginator;
use EsOrm\Tests\Fixtures\User;
use EsOrm\Tests\TestCase;

final class CursorPaginatorTest extends TestCase
{
    private function models(): ModelCollection
    {
        return new ModelCollection([
            (new User())->newInstance(['id' => '1', 'name' => 'Alice'], true),
            (new User())->newInstance(['id' => '2', 'name' => 'Bob'], true),
        ]);
    }

    public function test_it_exposes_the_cursor_and_metadata(): void
    {
        // Act
        $paginator = new CursorPaginator($this->models(), 2, ['2', 'abc'], true);

        // Assert
        self::assertSame(2, $paginator->perPage());
        self::assertSame(['2', 'abc'], $paginator->nextCursor());
        self::assertTrue($paginator->hasMorePages());
        self::assertSame(2, $paginator->count());
    }

    public function test_next_cursor_is_null_on_the_last_page(): void
    {
        // Act
        $paginator = new CursorPaginator($this->models(), 2, null, false);

        // Assert
        self::assertNull($paginator->nextCursor());
        self::assertFalse($paginator->hasMorePages());
    }

    public function test_first_and_last_return_the_boundary_items(): void
    {
        // Act
        $paginator = new CursorPaginator($this->models(), 2, null, false);

        // Assert
        self::assertSame('Alice', $paginator->first()->getAttribute('name'));
        self::assertSame('Bob', $paginator->last()->getAttribute('name'));
    }

    public function test_an_empty_cursor_page_is_empty(): void
    {
        // Act
        $paginator = new CursorPaginator(new ModelCollection(), 10, null, false);

        // Assert
        self::assertTrue($paginator->isEmpty());
        self::assertNull($paginator->first());
        self::assertNull($paginator->last());
        self::assertSame(0, $paginator->count());
    }

    public function test_to_array_serialises_the_cursor_page(): void
    {
        // Act
        $paginator = new CursorPaginator($this->models(), 2, ['2', 'abc'], true);

        // Assert
        self::assertSame([
            'data' => [
                ['id' => '1', 'name' => 'Alice'],
                ['id' => '2', 'name' => 'Bob'],
            ],
            'per_page' => 2,
            'next_cursor' => ['2', 'abc'],
            'has_more' => true,
        ], $paginator->toArray());
    }

    public function test_json_serialisation_matches_to_array(): void
    {
        // Act
        $paginator = new CursorPaginator($this->models(), 2, ['2', 'abc'], true);

        // Assert
        self::assertJsonStringEqualsJsonString(
            json_encode($paginator->toArray(), JSON_THROW_ON_ERROR),
            $paginator->toJson(),
        );
        self::assertSame($paginator->toArray(), $paginator->jsonSerialize());
    }
}
