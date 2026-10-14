<?php

declare(strict_types=1);

namespace EsOrm\Tests\Integration\Query;

use EsOrm\Tests\Fixtures\IntegrationUser;
use EsOrm\Tests\Integration\IntegrationHelpers;
use EsOrm\Tests\Integration\IntegrationTestCase;

/**
 * Real window and cursor pagination against a live node.
 */
final class PaginationIntegrationTest extends IntegrationTestCase
{
    use IntegrationHelpers;

    private function seed(string $index): void
    {
        $this->seedUsers($index, [
            ['id' => '1', 'name' => 'U10', 'age' => 10],
            ['id' => '2', 'name' => 'U20', 'age' => 20],
            ['id' => '3', 'name' => 'U30', 'age' => 30],
            ['id' => '4', 'name' => 'U40', 'age' => 40],
            ['id' => '5', 'name' => 'U50', 'age' => 50],
        ]);
    }

    public function test_it_paginates_across_pages_with_total_metadata(): void
    {
        // Arrange
        $index = $this->createUserIndex('page_length');
        $this->seed($index);

        // Act
        $first = IntegrationUser::query()->orderBy('age', 'asc')->paginate(2, 1);

        // Assert
        self::assertSame(5, $first->total());
        self::assertSame(3, $first->lastPage());
        self::assertSame(1, $first->currentPage());
        self::assertSame(2, $first->perPage());
        self::assertTrue($first->hasMorePages());
        self::assertSame([10, 20], $first->items()->map(fn ($u) => $u->age)->all());

        // Act: last page only holds the remaining document.
        $last = IntegrationUser::query()->orderBy('age', 'asc')->paginate(2, 3);

        // Assert
        self::assertSame(1, $last->count());
        self::assertSame([50], $last->items()->map(fn ($u) => $u->age)->all());
        self::assertFalse($last->hasMorePages());
    }

    public function test_it_exposes_simple_pagination_has_more(): void
    {
        // Arrange
        $index = $this->createUserIndex('page_simple');
        $this->seed($index);

        // Act
        $page = IntegrationUser::query()->orderBy('age', 'asc')->simplePaginate(2, 1);

        // Assert
        self::assertSame(2, $page->count());
        self::assertTrue($page->hasMorePages());
    }

    public function test_it_paginates_with_a_cursor_round_trip(): void
    {
        // Arrange
        $index = $this->createUserIndex('page_cursor');
        $this->seed($index);

        // Act: page 1.
        $page1 = IntegrationUser::query()->orderBy('age', 'asc')->cursorPaginate(2);

        // Assert
        self::assertSame([10, 20], $page1->items()->map(fn ($u) => $u->age)->all());
        self::assertTrue($page1->hasMorePages());
        self::assertNotNull($page1->nextCursor());

        // Act: page 2 using page 1's cursor.
        $page2 = IntegrationUser::query()->orderBy('age', 'asc')->cursorPaginate(2, $page1->nextCursor());

        // Assert
        self::assertSame([30, 40], $page2->items()->map(fn ($u) => $u->age)->all());
        self::assertTrue($page2->hasMorePages());
        self::assertNotEquals($page1->nextCursor(), $page2->nextCursor());

        // Act: page 3 (last), using page 2's cursor.
        $page3 = IntegrationUser::query()->orderBy('age', 'asc')->cursorPaginate(2, $page2->nextCursor());

        // Assert
        self::assertSame([50], $page3->items()->map(fn ($u) => $u->age)->all());
        self::assertFalse($page3->hasMorePages());
        self::assertNull($page3->nextCursor());
    }

    public function test_cursor_pagination_never_overlaps_between_pages(): void
    {
        // Arrange
        $index = $this->createUserIndex('page_cursor_overlap');
        $this->seed($index);

        // Act: walk the whole result set through cursors.
        $ids = [];
        $cursor = null;
        do {
            $page = IntegrationUser::query()->orderBy('age', 'asc')->cursorPaginate(2, $cursor);
            foreach ($page->items()->all() as $user) {
                $ids[] = $user->getKey();
            }
            $cursor = $page->nextCursor();
        } while ($cursor !== null);

        // Assert: every document visited exactly once.
        self::assertSame(['1', '2', '3', '4', '5'], $ids);
    }
}
