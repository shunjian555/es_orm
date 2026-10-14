<?php

declare(strict_types=1);

namespace EsOrm\Tests\Unit\Model;

use EsOrm\Model\ModelCollection;
use EsOrm\Model\SearchResult;
use EsOrm\Tests\Fixtures\User;
use EsOrm\Tests\TestCase;
use InvalidArgumentException;
use stdClass;

final class ModelCollectionTest extends TestCase
{
    /**
     * @return array<int, User>
     */
    private function users(): array
    {
        return [
            (new User())->newInstance(['id' => '1', 'name' => 'Alice', 'age' => '30']),
            (new User())->newInstance(['id' => '2', 'name' => 'Bob', 'age' => '20']),
            (new User())->newInstance(['id' => '3', 'name' => 'Carol', 'age' => '40']),
        ];
    }

    private function collection(): ModelCollection
    {
        return new ModelCollection($this->users());
    }

    public function test_it_exposes_all_items_and_the_attached_result(): void
    {
        // Arrange
        $result = new SearchResult(raw: [], hits: new ModelCollection(), total: 5);
        $collection = new ModelCollection($this->users(), $result);

        // Act & Assert
        self::assertCount(3, $collection->all());
        self::assertSame($result, $collection->result());
    }

    public function test_it_returns_the_first_and_last_items(): void
    {
        // Arrange
        $collection = $this->collection();

        // Act & Assert
        self::assertSame('1', $collection->first()?->getKey());
        self::assertSame('3', $collection->last()?->getKey());
        self::assertNull((new ModelCollection())->first());
        self::assertNull((new ModelCollection())->last());
    }

    public function test_it_reports_empty_and_not_empty(): void
    {
        // Act & Assert
        self::assertTrue((new ModelCollection())->isEmpty());
        self::assertFalse((new ModelCollection())->isNotEmpty());
        self::assertFalse($this->collection()->isEmpty());
        self::assertTrue($this->collection()->isNotEmpty());
    }

    public function test_it_counts_items(): void
    {
        // Arrange
        $collection = $this->collection();

        // Act & Assert
        self::assertSame(3, $collection->count());
        self::assertCount(3, $collection);
    }

    public function test_it_maps_over_items_preserving_keys(): void
    {
        // Arrange
        $collection = $this->collection();

        // Act
        $mapped = $collection->map(static fn (User $user): stdClass => (object) ['id' => $user->getKey()]);

        // Assert
        self::assertInstanceOf(ModelCollection::class, $mapped);
        self::assertSame([0 => '1', 1 => '2', 2 => '3'], array_map(static fn (stdClass $row): string => $row->id, $mapped->all()));
    }

    public function test_it_filters_items(): void
    {
        // Arrange
        $collection = $this->collection();

        // Act
        $filtered = $collection->filter(static fn (User $user): bool => $user->getAttribute('age') > 25);

        // Assert
        self::assertSame(['1', '3'], $filtered->pluck('id'));
        self::assertSame([0, 2], array_keys($filtered->all()));
    }

    public function test_it_eaches_until_the_callback_returns_false(): void
    {
        // Arrange
        $collection = $this->collection();
        $seen = [];

        // Act
        $returned = $collection->each(static function (User $user) use (&$seen): bool {
            $seen[] = $user->getAttribute('name');

            return $user->getAttribute('name') !== 'Bob';
        });

        // Assert
        self::assertSame(['Alice', 'Bob'], $seen);
        self::assertSame($collection, $returned);
    }

    public function test_it_plucks_values_and_optionally_keys(): void
    {
        // Arrange
        $collection = $this->collection();

        // Act & Assert
        self::assertSame(['Alice', 'Bob', 'Carol'], $collection->pluck('name'));
        self::assertSame(['1' => 'Alice', '2' => 'Bob', '3' => 'Carol'], $collection->pluck('name', 'id'));
    }

    public function test_it_keeps_only_the_given_keys(): void
    {
        // Arrange
        $collection = $this->collection();

        // Act
        $only = $collection->only([0, 2]);

        // Assert
        self::assertSame(['1', '3'], $only->pluck('id'));
        self::assertSame([0, 2], array_keys($only->all()));
    }

    public function test_it_excludes_the_given_keys(): void
    {
        // Arrange
        $collection = $this->collection();

        // Act
        $except = $collection->except([1]);

        // Assert
        self::assertSame(['1', '3'], $except->pluck('id'));
        self::assertSame(['1', '3'], array_values(array_map(static fn (User $user): string => $user->getKey(), $except->all())));
    }

    public function test_it_tests_containment_by_identity_and_by_closure(): void
    {
        // Arrange
        $collection = $this->collection();
        $first = $collection->first();

        // Act & Assert
        self::assertTrue($collection->contains($first));
        self::assertTrue($collection->contains(static fn (User $user): bool => $user->getAttribute('name') === 'Bob'));
        self::assertFalse($collection->contains(new User()));
    }

    public function test_it_sorts_items_using_a_callback(): void
    {
        // Arrange
        $collection = $this->collection();

        // Act
        $sorted = $collection->sort(static fn (User $left, User $right): int => $left->getAttribute('age') <=> $right->getAttribute('age'));

        // Assert
        self::assertSame(['2', '1', '3'], $sorted->pluck('id'));
    }

    public function test_it_sorts_items_by_a_key_in_both_directions(): void
    {
        // Arrange
        $collection = $this->collection();

        // Act
        $ascending = $collection->sortBy('age');
        $descending = $collection->sortBy('age', SORT_REGULAR, true);

        // Assert
        self::assertSame(['2', '1', '3'], $ascending->pluck('id'));
        self::assertSame(['3', '1', '2'], $descending->pluck('id'));
    }

    public function test_it_resets_keys_with_values(): void
    {
        // Arrange
        $collection = $this->collection()->except([1]);

        // Act
        $values = $collection->values();

        // Assert
        self::assertSame([0 => '1', 1 => '3'], array_map(static fn (User $user): string => $user->getKey(), $values->all()));
    }

    public function test_it_serializes_to_array(): void
    {
        // Arrange
        $collection = $this->collection();

        // Act
        $array = $collection->toArray();

        // Assert
        self::assertSame([
            ['id' => '1', 'name' => 'Alice', 'age' => 30],
            ['id' => '2', 'name' => 'Bob', 'age' => 20],
            ['id' => '3', 'name' => 'Carol', 'age' => 40],
        ], $array);
    }

    public function test_it_serializes_to_json(): void
    {
        // Arrange
        $collection = $this->collection();

        // Act
        $json = $collection->toJson();

        // Assert
        self::assertJsonStringEqualsJsonString('[{"id":"1","name":"Alice","age":30},{"id":"2","name":"Bob","age":20},{"id":"3","name":"Carol","age":40}]', $json);
    }

    public function test_it_reads_offsets(): void
    {
        // Arrange
        $collection = $this->collection();

        // Act & Assert
        self::assertTrue(isset($collection[0]));
        self::assertSame('1', $collection[0]->getKey());
        self::assertNull($collection[99]);
        self::assertFalse(isset($collection[99]));
    }

    public function test_it_writes_and_unsets_offsets(): void
    {
        // Arrange
        $collection = new ModelCollection();
        $user = (new User())->newInstance(['id' => '5'], true);

        // Act
        $collection[] = $user;
        $collection['k'] = $user;

        // Assert
        self::assertSame('5', $collection[0]->getKey());
        self::assertSame($user, $collection['k']);

        // Act
        $collection[0] = null;
        unset($collection['k']);

        // Assert
        self::assertCount(0, $collection);
    }

    public function test_it_throws_when_writing_a_non_object_offset(): void
    {
        // Arrange
        $collection = new ModelCollection();
        $this->expectException(InvalidArgumentException::class);

        // Act
        $collection[0] = 'not-an-object';
    }

    public function test_it_is_iterable(): void
    {
        // Arrange
        $collection = $this->collection();

        // Act
        $ids = [];
        foreach ($collection as $user) {
            $ids[] = $user->getKey();
        }

        // Assert
        self::assertSame(['1', '2', '3'], $ids);
        self::assertCount(3, iterator_to_array($collection));
    }

    public function test_it_reads_total_and_aggregations_from_the_attached_search_result(): void
    {
        // Arrange
        $collection = $this->collection();
        $result = new SearchResult(
            raw: [],
            hits: $collection,
            aggregations: ['by_status' => ['buckets' => [['key' => 'active']]]],
            suggest: ['sugg' => ['x']],
            total: 99,
            scrollId: 'scroll-1',
            searchAfter: ['s1'],
        );

        // Act
        $collection->setResult($result);

        // Assert
        self::assertSame(99, $collection->total());
        self::assertSame(['by_status' => ['buckets' => [['key' => 'active']]]], $collection->aggregations());
        self::assertSame(['buckets' => [['key' => 'active']]], $collection->aggregation('by_status'));
        self::assertSame(['sugg' => ['x']], $collection->suggest());
        self::assertSame('scroll-1', $collection->scrollId());
        self::assertSame(['s1'], $collection->searchAfter());
    }

    public function test_it_falls_back_to_the_item_count_for_total_without_a_result(): void
    {
        // Arrange
        $collection = $this->collection();

        // Act & Assert
        self::assertSame(3, $collection->total());
        self::assertSame([], $collection->aggregations());
        self::assertNull($collection->aggregation('missing'));
        self::assertNull($collection->scrollId());
    }

    public function test_it_creates_a_collection_through_make(): void
    {
        // Arrange & Act
        $collection = ModelCollection::make($this->users());

        // Assert
        self::assertCount(3, $collection);
    }
}
