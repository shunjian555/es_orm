<?php

declare(strict_types=1);

namespace EsOrm\Tests\Unit\Pagination;

use EsOrm\Model\ModelCollection;
use EsOrm\Query\Pagination\LengthAwarePaginator;
use EsOrm\Tests\Fixtures\User;
use EsOrm\Tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

final class LengthAwarePaginatorTest extends TestCase
{
    private function models(): ModelCollection
    {
        return new ModelCollection([
            (new User())->newInstance(['id' => '1', 'name' => 'Alice'], true),
            (new User())->newInstance(['id' => '2', 'name' => 'Bob'], true),
        ]);
    }

    public function test_it_exposes_total_and_page_metadata(): void
    {
        // Act
        $paginator = new LengthAwarePaginator($this->models(), 25, 10, 2);

        // Assert
        self::assertSame(25, $paginator->total());
        self::assertSame(10, $paginator->perPage());
        self::assertSame(2, $paginator->currentPage());
        self::assertSame(3, $paginator->lastPage());
        self::assertTrue($paginator->hasMorePages());
        self::assertSame(2, $paginator->count());
    }

    /**
     * @return array<string, array{0: int, 1: int, 2: int}>
     */
    public static function lastPageProvider(): array
    {
        return [
            'exact division' => [20, 10, 2],
            'remainder' => [25, 10, 3],
            'single page' => [5, 10, 1],
            'empty result' => [0, 10, 1],
            'zero per page' => [25, 0, 1],
            'negative per page' => [25, -5, 1],
        ];
    }

    #[DataProvider('lastPageProvider')]
    public function test_last_page_is_computed_from_total_and_per_page(int $total, int $perPage, int $expected): void
    {
        // Act
        $paginator = new LengthAwarePaginator($this->models(), $total, $perPage, 1);

        // Assert
        self::assertSame($expected, $paginator->lastPage());
    }

    public function test_has_more_pages_is_false_on_the_last_page(): void
    {
        // Act
        $paginator = new LengthAwarePaginator($this->models(), 20, 10, 2);

        // Assert
        self::assertSame(2, $paginator->lastPage());
        self::assertFalse($paginator->hasMorePages());
    }

    public function test_first_and_last_return_the_boundary_items(): void
    {
        // Act
        $paginator = new LengthAwarePaginator($this->models(), 2, 10, 1);

        // Assert
        self::assertSame('Alice', $paginator->first()->getAttribute('name'));
        self::assertSame('Bob', $paginator->last()->getAttribute('name'));
    }

    public function test_an_empty_page_is_empty(): void
    {
        // Act
        $paginator = new LengthAwarePaginator(new ModelCollection(), 0, 10, 1);

        // Assert
        self::assertTrue($paginator->isEmpty());
        self::assertNull($paginator->first());
        self::assertNull($paginator->last());
    }

    public function test_to_array_serialises_the_page(): void
    {
        // Act
        $paginator = new LengthAwarePaginator($this->models(), 25, 10, 2);

        // Assert
        self::assertSame([
            'data' => [
                ['id' => '1', 'name' => 'Alice'],
                ['id' => '2', 'name' => 'Bob'],
            ],
            'total' => 25,
            'per_page' => 10,
            'current_page' => 2,
            'last_page' => 3,
        ], $paginator->toArray());
    }

    public function test_json_serialisation_matches_to_array(): void
    {
        // Act
        $paginator = new LengthAwarePaginator($this->models(), 25, 10, 2);

        // Assert
        self::assertJsonStringEqualsJsonString(
            json_encode($paginator->toArray(), JSON_THROW_ON_ERROR),
            $paginator->toJson(),
        );
        self::assertSame($paginator->toArray(), $paginator->jsonSerialize());
    }
}
