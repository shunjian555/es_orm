<?php

declare(strict_types=1);

namespace EsOrm\Tests\Unit\Model;

use EsOrm\Model\ModelCollection;
use EsOrm\Model\SearchResult;
use EsOrm\Tests\Fixtures\User;
use EsOrm\Tests\TestCase;

final class SearchResultTest extends TestCase
{
    private function hits(): ModelCollection
    {
        return new ModelCollection([
            (new User())->newInstance(['id' => '1', 'name' => 'Alice'], true),
            (new User())->newInstance(['id' => '2', 'name' => 'Bob'], true),
        ]);
    }

    public function test_it_builds_from_a_raw_response(): void
    {
        // Arrange
        $response = [
            'took' => 7,
            'timed_out' => true,
            'hits' => [
                'total' => ['value' => 42],
                'hits' => [
                    ['_id' => '1', '_source' => ['name' => 'A'], 'sort' => [10, 'a']],
                    ['_id' => '2', '_source' => ['name' => 'B'], 'sort' => [20, 'b']],
                ],
            ],
            'aggregations' => ['by_status' => ['buckets' => []]],
            'suggest' => ['sugg' => ['x']],
            '_scroll_id' => 'scroll-abc',
        ];
        $hits = $this->hits();

        // Act
        $result = SearchResult::fromResponse($response, $hits, 'pit-1');

        // Assert
        self::assertSame(42, $result->total());
        self::assertSame(['by_status' => ['buckets' => []]], $result->aggregations());
        self::assertSame(['sugg' => ['x']], $result->suggest());
        self::assertSame('scroll-abc', $result->scrollId());
        self::assertSame('pit-1', $result->pitId());
        self::assertSame([20, 'b'], $result->searchAfter());
        self::assertSame(7, $result->took());
        self::assertTrue($result->timedOut());
        self::assertSame($response, $result->raw());
        self::assertSame($hits, $result->hits());
    }

    public function test_it_reads_a_single_aggregation_by_name(): void
    {
        // Arrange
        $response = ['aggregations' => ['by_status' => ['buckets' => [['key' => 'active']]]]];
        $result = SearchResult::fromResponse($response, $this->hits());

        // Act & Assert
        self::assertSame(['buckets' => [['key' => 'active']]], $result->aggregation('by_status'));
        self::assertNull($result->aggregation('missing'));
    }

    public function test_it_falls_back_to_the_hit_count_when_total_is_absent(): void
    {
        // Arrange
        $response = ['hits' => ['hits' => [['_id' => '1'], ['_id' => '2']]]];

        // Act
        $result = SearchResult::fromResponse($response, $this->hits());

        // Assert
        self::assertSame(2, $result->total());
        self::assertSame(0, $result->took());
        self::assertFalse($result->timedOut());
        self::assertNull($result->scrollId());
        self::assertNull($result->pitId());
    }

    public function test_its_search_after_cursor_is_empty_without_a_sorted_last_hit(): void
    {
        // Arrange
        $response = ['hits' => ['hits' => [['_id' => '1', '_source' => []]]]];

        // Act
        $result = SearchResult::fromResponse($response, $this->hits());

        // Assert
        self::assertSame([], $result->searchAfter());
    }

    public function test_its_search_after_cursor_is_empty_for_an_empty_response(): void
    {
        // Arrange
        $response = ['hits' => ['hits' => []]];

        // Act
        $result = SearchResult::fromResponse($response, new ModelCollection());

        // Assert
        self::assertSame([], $result->searchAfter());
        self::assertSame(0, $result->total());
    }

    public function test_it_counts_and_iterates_the_hydrated_hits(): void
    {
        // Arrange
        $result = SearchResult::fromResponse(['hits' => ['hits' => []]], $this->hits());

        // Act
        $ids = [];
        foreach ($result as $user) {
            $ids[] = $user->getKey();
        }

        // Assert
        self::assertSame(2, $result->count());
        self::assertCount(2, $result);
        self::assertSame(['1', '2'], $ids);
    }

    public function test_it_reads_and_writes_the_underlying_offsets(): void
    {
        // Arrange
        $result = SearchResult::fromResponse(['hits' => ['hits' => []]], $this->hits());
        $replacement = (new User())->newInstance(['id' => '9'], true);

        // Act
        $result[0] = $replacement;

        // Assert
        self::assertTrue(isset($result[0]));
        self::assertSame('9', $result[0]->getKey());
        self::assertSame('2', $result->hits()->offsetGet(1)->getKey());
    }

    public function test_it_serializes_the_hydrated_hits(): void
    {
        // Arrange
        $result = SearchResult::fromResponse(['hits' => ['hits' => []]], $this->hits());

        // Act & Assert
        self::assertSame([['id' => '1', 'name' => 'Alice'], ['id' => '2', 'name' => 'Bob']], $result->toArray());
        self::assertJsonStringEqualsJsonString('[{"id":"1","name":"Alice"},{"id":"2","name":"Bob"}]', $result->toJson());
    }
}
