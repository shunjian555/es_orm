<?php

declare(strict_types=1);

namespace EsOrm\Tests\Unit\Model;

use EsOrm\Model\ModelCollection;
use EsOrm\Model\SearchResult;
use EsOrm\Tests\Fixtures\User;
use EsOrm\Tests\TestCase;

/**
 * Covers the {@see SearchResult} surface not exercised elsewhere: cursorFrom(),
 * iteration and ArrayAccess delegation to the underlying hits.
 */
final class SearchResultAccessTest extends TestCase
{
    private function makeResult(array $documents = []): SearchResult
    {
        $hits = [];
        foreach ($documents as $document) {
            $hits[] = (new User())->newFromHit($document);
        }

        return SearchResult::fromResponse(
            ['hits' => ['total' => ['value' => count($documents)], 'hits' => $documents]],
            new ModelCollection($hits),
        );
    }

    public function test_cursor_from_extracts_the_sort_values_of_the_last_document(): void
    {
        // Act
        $cursor = SearchResult::cursorFrom([
            ['_id' => '1', 'sort' => [10]],
            ['_id' => '2', 'sort' => [20]],
        ]);

        // Assert
        self::assertSame([20], $cursor);
    }

    public function test_cursor_from_returns_an_empty_array_without_sort_values(): void
    {
        // Act + Assert
        self::assertSame([], SearchResult::cursorFrom([['id' => '1']]));
        self::assertSame([], SearchResult::cursorFrom([]));
        self::assertSame([], SearchResult::cursorFrom(['not-an-array']));
    }

    public function test_it_is_iterable_over_the_hits(): void
    {
        // Act
        $result = $this->makeResult([
            ['_id' => '1', '_source' => ['name' => 'Alice']],
            ['_id' => '2', '_source' => ['name' => 'Bob']],
        ]);

        $names = [];
        foreach ($result as $model) {
            $names[] = $model->getAttribute('name');
        }

        // Assert
        self::assertSame(['Alice', 'Bob'], $names);
        self::assertCount(2, $result);
    }

    public function test_it_delegates_array_access_to_the_hits(): void
    {
        // Arrange
        $result = $this->makeResult([
            ['_id' => '1', '_source' => ['name' => 'Alice']],
        ]);

        // Assert: existing hit
        self::assertTrue(isset($result[0]));
        self::assertSame('Alice', $result[0]->getAttribute('name'));
        self::assertNull($result[99] ?? null);
        self::assertFalse(isset($result[99]));

        // Act + Assert: appended hit becomes readable
        $result[1] = (new User())->setAttribute('name', 'Carol');
        self::assertSame('Carol', $result[1]->getAttribute('name'));

        unset($result[1]);
        self::assertFalse(isset($result[1]));
    }
}
