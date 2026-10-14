<?php

declare(strict_types=1);

namespace EsOrm\Tests\Feature\Search;

use EsOrm\Tests\Fixtures\IntegrationUser;
use EsOrm\Tests\Integration\IntegrationHelpers;
use EsOrm\Tests\Integration\IntegrationTestCase;

/**
 * Sorting, `_source` filtering and highlighting, combined in one real request.
 */
final class SearchFeatureTest extends IntegrationTestCase
{
    use IntegrationHelpers;

    private function seed(string $index): void
    {
        $this->seedUsers($index, [
            ['id' => '1', 'name' => 'Alice', 'email' => 'a@example.com', 'age' => 20],
            ['id' => '2', 'name' => 'Bob', 'email' => 'b@example.com', 'age' => 40],
            ['id' => '3', 'name' => 'Carol', 'email' => 'c@example.com', 'age' => 30],
        ]);
    }

    public function test_it_sorts_documents_and_filters_the_source(): void
    {
        // Arrange
        $index = $this->createUserIndex('feature_sort_source');
        $this->seed($index);

        // Act
        $result = IntegrationUser::query()
            ->orderByDesc('age')
            ->select(['name', 'age'])
            ->get();

        // Assert: ordering by age descending.
        self::assertSame([40, 30, 20], $result->map(fn ($u) => $u->age)->all());

        // Assert: only the selected fields (plus the injected id) are present.
        $first = $result->first();
        $array = $first->toArray();
        self::assertArrayHasKey('name', $array);
        self::assertArrayHasKey('age', $array);
        self::assertArrayNotHasKey('email', $array);
    }

    public function test_it_highlights_matching_fields_with_custom_tags(): void
    {
        // Arrange
        $index = $this->createUserIndex('feature_highlight');
        $this->seed($index);

        // Act
        $result = IntegrationUser::query()
            ->match('name', 'Alice')
            ->highlight('name', [
                'pre_tags' => ['<b>'],
                'post_tags' => ['</b>'],
            ])
            ->get();

        // Assert
        self::assertSame(1, $result->count());
        $hit = $result->first();
        self::assertSame('Alice', $hit->name);

        $fragments = $hit->highlight('name');
        self::assertIsArray($fragments);
        self::assertNotSame([], $fragments);
        self::assertStringContainsString('<b>Alice</b>', implode('', $fragments));
    }
}
