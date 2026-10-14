<?php

declare(strict_types=1);

namespace EsOrm\Tests\Integration\Query;

use EsOrm\Tests\Fixtures\IntegrationUser;
use EsOrm\Tests\Integration\IntegrationHelpers;
use EsOrm\Tests\Integration\IntegrationTestCase;

/**
 * Real search requests (bool query combinations) against a live node.
 */
final class SearchIntegrationTest extends IntegrationTestCase
{
    use IntegrationHelpers;

    /**
     * A deterministic dataset shared by the search assertions.
     *
     * @param list<array<string, mixed>> $documents
     */
    private function seed(string $index): void
    {
        $this->seedUsers($index, [
            ['id' => '1', 'name' => 'Alice', 'status' => 'active', 'city' => 'Paris', 'age' => 30],
            ['id' => '2', 'name' => 'Bob', 'status' => 'active', 'city' => 'Berlin', 'age' => 25],
            ['id' => '3', 'name' => 'Carol', 'status' => 'inactive', 'city' => 'Paris', 'age' => 40],
            ['id' => '4', 'name' => 'Dave', 'status' => 'inactive', 'city' => 'Rome', 'age' => 20],
        ]);
    }

    public function test_it_searches_with_a_must_bool_query(): void
    {
        // Arrange
        $index = $this->createUserIndex('search_must');
        $this->seed($index);

        // Act
        $result = IntegrationUser::query()
            ->where('status', 'active')
            ->orderBy('age', 'asc')
            ->get();

        // Assert
        self::assertSame(2, $result->count());
        self::assertSame(['Bob', 'Alice'], $result->map(fn ($user) => $user->name)->all());
    }

    public function test_it_combines_must_and_must_not_clauses(): void
    {
        // Arrange
        $index = $this->createUserIndex('search_bool');
        $this->seed($index);

        // Act
        $result = IntegrationUser::query()
            ->term('city', 'Paris')
            ->whereNotIn('status', ['inactive'])
            ->get();

        // Assert
        self::assertSame(1, $result->count());
        self::assertSame('Alice', $result->first()->name);
    }

    public function test_it_supports_orWhere_semantics(): void
    {
        // Arrange
        $index = $this->createUserIndex('search_or');
        $this->seed($index);

        // Act
        $result = IntegrationUser::query()
            ->where('city', 'Berlin')
            ->orWhere('city', 'Rome')
            ->get();

        // Assert
        self::assertNotSame([], $result->all());
        self::assertSame(2, $result->count());
    }

    public function test_it_filters_with_a_range_query(): void
    {
        // Arrange
        $index = $this->createUserIndex('search_range');
        $this->seed($index);

        // Act
        $result = IntegrationUser::query()
            ->whereBetween('age', [25, 30])
            ->orderBy('age', 'asc')
            ->get();

        // Assert
        self::assertSame(2, $result->count());
        self::assertSame([25, 30], $result->map(fn ($user) => $user->age)->all());
    }

    public function test_it_counts_matching_documents(): void
    {
        // Arrange
        $index = $this->createUserIndex('search_count');
        $this->seed($index);

        // Act
        $count = IntegrationUser::query()->where('city', 'Paris')->count();

        // Assert
        self::assertSame(2, $count);
    }

    public function test_it_matches_documents_by_text(): void
    {
        // Arrange
        $index = $this->createUserIndex('search_match');
        $this->seed($index);

        // Act
        $result = IntegrationUser::query()->match('name', 'Alice')->get();

        // Assert
        self::assertSame(1, $result->count());
        self::assertSame('Alice', $result->first()->name);
    }

    public function test_it_finds_documents_by_terms_and_exists(): void
    {
        // Arrange
        $index = $this->createUserIndex('search_terms');
        $this->seed($index);

        // Act: active OR active redundant terms + required field existence.
        $result = IntegrationUser::query()
            ->terms('status', ['active'])
            ->whereNotNull('city')
            ->get();

        // Assert
        self::assertSame(2, $result->count());
    }
}
