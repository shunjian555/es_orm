<?php

declare(strict_types=1);

namespace EsOrm\Tests\Unit\Pagination;

use EsOrm\Model\ModelCollection;
use EsOrm\Query\Pagination\Paginator;
use EsOrm\Tests\Fixtures\User;
use EsOrm\Tests\TestCase;

final class PaginatorTest extends TestCase
{
    /**
     * @return ModelCollection
     */
    private function models(): ModelCollection
    {
        return new ModelCollection([
            (new User())->newInstance(['id' => '1', 'name' => 'Alice'], true),
            (new User())->newInstance(['id' => '2', 'name' => 'Bob'], true),
        ]);
    }

    public function test_it_exposes_the_items_and_the_page_metadata(): void
    {
        // Arrange
        $items = $this->models();

        // Act
        $paginator = new Paginator($items, 10, 2, true);

        // Assert
        self::assertSame($items, $paginator->items());
        self::assertSame(10, $paginator->perPage());
        self::assertSame(2, $paginator->currentPage());
        self::assertTrue($paginator->hasMorePages());
        self::assertSame(2, $paginator->count());
    }

    public function test_first_and_last_return_the_boundary_items(): void
    {
        // Act
        $paginator = new Paginator($this->models(), 10, 1, false);

        // Assert
        self::assertSame('Alice', $paginator->first()->getAttribute('name'));
        self::assertSame('Bob', $paginator->last()->getAttribute('name'));
    }

    public function test_an_empty_page_exposes_no_items(): void
    {
        // Act
        $paginator = new Paginator(new ModelCollection(), 10, 1, false);

        // Assert
        self::assertTrue($paginator->isEmpty());
        self::assertNull($paginator->first());
        self::assertNull($paginator->last());
        self::assertSame(0, $paginator->count());
    }

    public function test_to_array_serialises_the_page(): void
    {
        // Act
        $paginator = new Paginator($this->models(), 10, 2, true);

        // Assert
        self::assertSame([
            'data' => [
                ['id' => '1', 'name' => 'Alice'],
                ['id' => '2', 'name' => 'Bob'],
            ],
            'per_page' => 10,
            'current_page' => 2,
            'has_more' => true,
        ], $paginator->toArray());
    }

    public function test_json_serialisation_matches_to_array(): void
    {
        // Act
        $paginator = new Paginator($this->models(), 10, 2, true);

        // Assert
        self::assertJsonStringEqualsJsonString(
            json_encode($paginator->toArray(), JSON_THROW_ON_ERROR),
            $paginator->toJson(),
        );
        self::assertSame($paginator->toArray(), $paginator->jsonSerialize());
    }

    public function test_it_is_iterable_over_its_items(): void
    {
        // Act
        $paginator = new Paginator($this->models(), 10, 1, false);

        // Assert
        self::assertSame(
            ['Alice', 'Bob'],
            array_map(
                static fn (User $user): string => $user->getAttribute('name'),
                iterator_to_array($paginator),
            ),
        );
    }
}
