<?php

declare(strict_types=1);

namespace EsOrm\Tests\Integration\Query;

use EsOrm\Tests\Fixtures\IntegrationUser;
use EsOrm\Tests\Integration\IntegrationHelpers;
use EsOrm\Tests\Integration\IntegrationTestCase;

/**
 * Real metric/bucket aggregations executed by Elasticsearch.
 */
final class AggregationIntegrationTest extends IntegrationTestCase
{
    use IntegrationHelpers;

    private function seed(string $index): void
    {
        $this->seedUsers($index, [
            ['id' => '1', 'name' => 'Alice', 'status' => 'active', 'age' => 20],
            ['id' => '2', 'name' => 'Bob', 'status' => 'active', 'age' => 30],
            ['id' => '3', 'name' => 'Carol', 'status' => 'active', 'age' => 40],
            ['id' => '4', 'name' => 'Dave', 'status' => 'inactive', 'age' => 50],
        ]);
    }

    public function test_it_runs_a_terms_aggregation_with_the_right_doc_counts(): void
    {
        // Arrange
        $index = $this->createUserIndex('agg_terms');
        $this->seed($index);

        // Act
        $result = IntegrationUser::query()->termsAgg('by_status', 'status')->search();
        $buckets = $result->aggregation('by_status')['buckets'];

        // Assert
        self::assertSame(2, count($buckets));
        $counts = [];
        foreach ($buckets as $bucket) {
            $counts[$bucket['key']] = $bucket['doc_count'];
        }
        self::assertSame(['active' => 3, 'inactive' => 1], $counts);
    }

    public function test_it_runs_a_stats_aggregation(): void
    {
        // Arrange
        $index = $this->createUserIndex('agg_stats');
        $this->seed($index);

        // Act
        $result = IntegrationUser::query()->statsAgg('age_stats', 'age')->search();
        $stats = $result->aggregation('age_stats');

        // Assert
        self::assertSame(4.0, (float) $stats['count']);
        self::assertSame(20.0, (float) $stats['min']);
        self::assertSame(50.0, (float) $stats['max']);
        self::assertSame(140.0, (float) $stats['sum']);
    }

    public function test_it_runs_an_avg_aggregation(): void
    {
        // Arrange
        $index = $this->createUserIndex('agg_avg');
        $this->seed($index);

        // Act
        $result = IntegrationUser::query()->avgAgg('avg_age', 'age')->search();

        // Assert
        self::assertSame(35.0, (float) $result->aggregation('avg_age')['value']);
    }

    public function test_it_scopes_an_aggregation_to_a_query(): void
    {
        // Arrange
        $index = $this->createUserIndex('agg_scoped');
        $this->seed($index);

        // Act
        $result = IntegrationUser::query()
            ->where('status', 'active')
            ->avgAgg('avg_age', 'age')
            ->search();

        // Assert
        self::assertSame(30.0, (float) $result->aggregation('avg_age')['value']);
        self::assertSame(3, $result->total());
    }

    public function test_it_returns_aggregations_through_get_collection(): void
    {
        // Arrange
        $index = $this->createUserIndex('agg_collection');
        $this->seed($index);

        // Act
        $collection = IntegrationUser::query()->termsAgg('by_status', 'status')->get();

        // Assert
        self::assertSame(3, $collection->aggregation('by_status')['buckets'][0]['doc_count']);
    }
}
