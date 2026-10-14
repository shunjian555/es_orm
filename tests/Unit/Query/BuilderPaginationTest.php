<?php

declare(strict_types=1);

namespace EsOrm\Tests\Unit\Query;

use EsOrm\Tests\Fixtures\User;
use EsOrm\Tests\TestCase;

/**
 * Covers windowing (limit/offset/searchAfter/pit/scroll) and pagination.
 */
final class BuilderPaginationTest extends TestCase
{
    public function test_limit_sets_the_size(): void
    {
        // Act
        $dsl = User::query()->limit(5)->toDsl();

        // Assert
        self::assertSame(5, $dsl['size']);
    }

    public function test_offset_sets_the_from(): void
    {
        // Act
        $dsl = User::query()->offset(10)->toDsl();

        // Assert
        self::assertSame(10, $dsl['from']);
    }

    public function test_offset_is_clamped_to_zero(): void
    {
        // Act
        $dsl = User::query()->offset(-5)->toDsl();

        // Assert
        self::assertArrayNotHasKey('from', $dsl);
    }

    public function test_limit_and_offset_are_combined(): void
    {
        // Act
        $dsl = User::query()->limit(5)->offset(10)->toDsl();

        // Assert
        self::assertSame(5, $dsl['size']);
        self::assertSame(10, $dsl['from']);
    }

    public function test_search_after_adds_the_cursor(): void
    {
        // Act
        $dsl = User::query()->orderBy('id')->searchAfter([10, 'abc'])->toDsl();

        // Assert
        self::assertSame([10, 'abc'], $dsl['search_after']);
    }

    public function test_pit_adds_the_pit_node_with_keep_alive(): void
    {
        // Act
        $dsl = User::query()->pit('pit-1', '5m')->toDsl();

        // Assert
        self::assertEquals(['id' => 'pit-1', 'keep_alive' => '5m'], $dsl['pit']);
    }

    public function test_pit_omits_the_index_from_request_params(): void
    {
        // Arrange
        $this->client->setResponse('search', ['hits' => ['total' => ['value' => 0], 'hits' => []]]);

        // Act
        User::query()->pit('pit-1')->get();

        // Assert
        $params = $this->client->lastParams('search');
        self::assertArrayNotHasKey('index', $params);
        self::assertEquals(['id' => 'pit-1', 'keep_alive' => '1m'], $params['body']['pit']);
    }

    public function test_scroll_sets_size_and_omits_track_total_hits(): void
    {
        // Act
        $dsl = User::query()->scroll(50, '2m')->toDsl();

        // Assert
        self::assertSame(50, $dsl['size']);
        self::assertArrayNotHasKey('track_total_hits', $dsl);
    }

    public function test_scroll_adds_the_scroll_keep_alive_to_request_params(): void
    {
        // Arrange
        $this->client->setResponse('search', ['hits' => ['total' => ['value' => 0], 'hits' => []]]);

        // Act
        User::query()->scroll(50, '2m')->get();

        // Assert
        self::assertSame('2m', $this->client->lastParams('search')['scroll']);
    }

    public function test_paginate_computes_the_from_and_size_and_returns_a_length_aware_page(): void
    {
        // Arrange
        $this->client->setResponse('search', [
            'took' => 1,
            'hits' => [
                'total' => ['value' => 3],
                'hits' => [
                    ['_id' => '1', '_source' => ['name' => 'A']],
                    ['_id' => '2', '_source' => ['name' => 'B']],
                ],
            ],
        ]);

        // Act
        $paginator = User::query()->paginate(2, 2);

        // Assert
        $params = $this->client->lastParams('search');
        self::assertSame(2, $params['body']['from']);
        self::assertSame(2, $params['body']['size']);
        self::assertSame(2, $paginator->currentPage());
        self::assertSame(2, $paginator->perPage());
        self::assertSame(3, $paginator->total());
        self::assertSame(2, $paginator->lastPage());
        self::assertCount(2, $paginator->items());
    }

    public function test_simple_paginate_requests_one_extra_record_to_detect_more_pages(): void
    {
        // Arrange
        $this->client->setResponse('search', [
            'hits' => [
                'total' => ['value' => 3],
                'hits' => [
                    ['_id' => '1', '_source' => ['name' => 'A']],
                    ['_id' => '2', '_source' => ['name' => 'B']],
                    ['_id' => '3', '_source' => ['name' => 'C']],
                ],
            ],
        ]);

        // Act
        $paginator = User::query()->simplePaginate(2, 1);

        // Assert
        self::assertSame(3, $this->client->lastParams('search')['body']['size']);
        self::assertCount(2, $paginator->items());
        self::assertTrue($paginator->hasMorePages());
    }

    public function test_simple_paginate_reports_no_more_pages_when_the_extra_record_is_absent(): void
    {
        // Arrange
        $this->client->setResponse('search', [
            'hits' => [
                'total' => ['value' => 2],
                'hits' => [
                    ['_id' => '1', '_source' => ['name' => 'A']],
                    ['_id' => '2', '_source' => ['name' => 'B']],
                ],
            ],
        ]);

        // Act
        $paginator = User::query()->simplePaginate(2, 1);

        // Assert
        self::assertFalse($paginator->hasMorePages());
        self::assertCount(2, $paginator->items());
    }

    public function test_cursor_paginate_adds_a_tie_breaker_sort(): void
    {
        // Arrange
        $this->client->setResponse('search', [
            'hits' => ['total' => ['value' => 0], 'hits' => []],
        ]);

        // Act
        User::query()->cursorPaginate(2);

        // Assert
        self::assertEquals([['id' => 'asc']], $this->client->lastParams('search')['body']['sort']);
    }

    public function test_cursor_paginate_requests_one_extra_item_and_trims_to_per_page(): void
    {
        // Arrange
        $this->client->setResponse('search', [
            'hits' => [
                'total' => ['value' => 3],
                'hits' => [
                    ['_id' => '1', '_source' => ['name' => 'A'], 'sort' => [1]],
                    ['_id' => '2', '_source' => ['name' => 'B'], 'sort' => [2]],
                    ['_id' => '3', '_source' => ['name' => 'C'], 'sort' => [3]],
                ],
            ],
        ]);

        // Act
        $paginator = User::query()->cursorPaginate(2);

        // Assert
        self::assertTrue($paginator->hasMorePages());
        self::assertCount(2, $paginator->items());
        self::assertSame(3, $this->client->lastParams('search')['body']['size']);
    }

    /**
     * The look-ahead (perPage + 1) document is fetched only to detect whether a
     * next page exists, so the next cursor must point at the LAST RETURNED item
     * ([2]) and not at the look-ahead item ([3]) — otherwise the following page
     * would skip document 3.
     */
    public function test_cursor_paginate_next_cursor_points_at_the_last_returned_item(): void
    {
        // Arrange
        $this->client->setResponse('search', [
            'hits' => [
                'total' => ['value' => 3],
                'hits' => [
                    ['_id' => '1', '_source' => ['name' => 'A'], 'sort' => [1]],
                    ['_id' => '2', '_source' => ['name' => 'B'], 'sort' => [2]],
                    ['_id' => '3', '_source' => ['name' => 'C'], 'sort' => [3]],
                ],
            ],
        ]);

        // Act
        $paginator = User::query()->cursorPaginate(2);

        // Assert
        self::assertSame([2], $paginator->nextCursor());
    }

    public function test_cursor_paginate_forwards_the_cursor_to_search_after(): void
    {
        // Arrange
        $this->client->setResponse('search', ['hits' => ['total' => ['value' => 0], 'hits' => []]]);

        // Act
        User::query()->cursorPaginate(2, [5]);

        // Assert
        self::assertSame([5], $this->client->lastParams('search')['body']['search_after']);
    }
}
