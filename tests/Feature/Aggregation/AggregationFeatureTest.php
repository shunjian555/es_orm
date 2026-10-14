<?php

declare(strict_types=1);

namespace EsOrm\Tests\Feature\Aggregation;

use EsOrm\Tests\Fixtures\IntegrationUser;
use EsOrm\Tests\Integration\IntegrationHelpers;
use EsOrm\Tests\Integration\IntegrationTestCase;

/**
 * Nested aggregations, sub-aggregations and aggregation/query combinations.
 */
final class AggregationFeatureTest extends IntegrationTestCase
{
    use IntegrationHelpers;

    private function seed(string $index): void
    {
        $this->seedUsers($index, [
            [
                'id' => '1',
                'name' => 'Alice',
                'status' => 'active',
                'age' => 20,
                'profile' => [
                    ['skill' => 'php', 'city' => 'Paris'],
                    ['skill' => 'es', 'city' => 'Paris'],
                ],
            ],
            [
                'id' => '2',
                'name' => 'Bob',
                'status' => 'active',
                'age' => 40,
                'profile' => [
                    ['skill' => 'php', 'city' => 'Berlin'],
                ],
            ],
            [
                'id' => '3',
                'name' => 'Carol',
                'status' => 'inactive',
                'age' => 50,
                'profile' => [
                    ['skill' => 'go', 'city' => 'Rome'],
                ],
            ],
        ]);
    }

    public function test_it_runs_a_nested_aggregation_with_a_terms_sub_aggregation(): void
    {
        // Arrange
        $index = $this->createUserIndex('feature_agg_nested');
        $this->seed($index);

        // Act
        $result = IntegrationUser::query()
            ->aggregate('skills', function ($agg): void {
                $agg->nested('profiles', 'profile');
                $agg->terms('skill_counts', 'profile.skill');
            })
            ->search();

        // Assert: the nested aggregation contains a sub terms aggregation.
        $skills = $result->aggregation('skills');
        self::assertArrayHasKey('skill_counts', $skills);

        $counts = [];
        foreach ($skills['skill_counts']['buckets'] as $bucket) {
            $counts[$bucket['key']] = $bucket['doc_count'];
        }
        self::assertSame(2, $counts['php']);
        self::assertSame(1, $counts['es']);
        self::assertSame(1, $counts['go']);
    }

    public function test_it_combines_terms_buckets_with_an_avg_sub_aggregation(): void
    {
        // Arrange
        $index = $this->createUserIndex('feature_agg_sub');
        $this->seed($index);

        // Act
        $result = IntegrationUser::query()
            ->aggregate('by_status', function ($agg): void {
                $agg->terms('status_buckets', 'status');
                $agg->avg('avg_age', 'age');
            })
            ->search();

        // Assert
        $buckets = [];
        foreach ($result->aggregation('by_status')['buckets'] as $bucket) {
            $buckets[$bucket['key']] = $bucket;
        }

        self::assertSame(2, $buckets['active']['doc_count']);
        self::assertSame(30.0, (float) $buckets['active']['avg_age']['value']);
        self::assertSame(1, $buckets['inactive']['doc_count']);
        self::assertSame(50.0, (float) $buckets['inactive']['avg_age']['value']);
    }

    public function test_a_query_and_an_aggregation_do_not_overwrite_each_other(): void
    {
        // Arrange
        $index = $this->createUserIndex('feature_agg_query');
        $this->seed($index);

        // Act: restrict the search to active users, then aggregate their ages.
        $result = IntegrationUser::query()
            ->where('status', 'active')
            ->statsAgg('age_stats', 'age')
            ->search();

        // Assert: only the filtered documents contributed to the stats.
        self::assertSame(2, $result->total());
        self::assertSame(2.0, (float) $result->aggregation('age_stats')['count']);
        self::assertSame(30.0, (float) $result->aggregation('age_stats')['avg']);
    }
}
