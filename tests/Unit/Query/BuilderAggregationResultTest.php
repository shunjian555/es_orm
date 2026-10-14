<?php

declare(strict_types=1);

namespace EsOrm\Tests\Unit\Query;

use EsOrm\Tests\Fixtures\User;
use EsOrm\Tests\TestCase;

/**
 * Regression test: the collection returned by get() must carry the search
 * metadata so `get()->aggregation()` works as documented in the README.
 */
final class BuilderAggregationResultTest extends TestCase
{
    public function test_the_collection_from_get_exposes_the_aggregations(): void
    {
        // Arrange
        $this->client->setResponse('search', [
            'took' => 2,
            'hits' => ['total' => ['value' => 2], 'hits' => [
                ['_id' => '1', '_source' => ['id' => '1', 'status' => 'active']],
            ]],
            'aggregations' => [
                'by_status' => ['buckets' => [
                    ['key' => 'active', 'doc_count' => 2],
                    ['key' => 'inactive', 'doc_count' => 1],
                ]],
            ],
        ]);

        // Act
        $collection = User::query()->termsAgg('by_status', 'status')->get();

        // Assert
        self::assertSame(2, $collection->aggregation('by_status')['buckets'][0]['doc_count']);
        self::assertSame(2, $collection->total());
        self::assertSame(['by_status'], array_keys($collection->aggregations()));
    }

    public function test_search_result_and_hits_share_the_same_aggregations(): void
    {
        // Arrange
        $this->client->setResponse('search', [
            'hits' => ['total' => ['value' => 0], 'hits' => []],
            'aggregations' => ['age_stats' => ['count' => 4, 'avg' => 35.0]],
        ]);

        // Act
        $result = User::query()->statsAgg('age_stats', 'age')->search();

        // Assert: the hits collection and the result expose the same metadata.
        self::assertSame($result->aggregations(), $result->hits()->aggregations());
        self::assertSame(35.0, $result->hits()->aggregation('age_stats')['avg']);
    }
}
