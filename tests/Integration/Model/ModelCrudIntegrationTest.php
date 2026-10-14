<?php

declare(strict_types=1);

namespace EsOrm\Tests\Integration\Model;

use DateTimeInterface;
use EsOrm\Exceptions\ModelNotFoundException;
use EsOrm\Tests\Fixtures\IntegrationUser;
use EsOrm\Tests\Integration\IntegrationHelpers;
use EsOrm\Tests\Integration\IntegrationTestCase;
use stdClass;

/**
 * Real Active Record lifecycle against a live Elasticsearch node.
 */
final class ModelCrudIntegrationTest extends IntegrationTestCase
{
    use IntegrationHelpers;

    public function test_it_creates_and_finds_a_document_by_id(): void
    {
        // Arrange
        $index = $this->createUserIndex('crud_create');

        // Act
        $created = IntegrationUser::create([
            'id' => '1',
            'name' => 'Alice',
            'email' => 'alice@example.com',
            'age' => 30,
            'status' => 'active',
        ]);
        $this->es('refresh', ['index' => $index]);

        $found = IntegrationUser::find('1');

        // Assert
        self::assertTrue($created->exists());
        self::assertInstanceOf(IntegrationUser::class, $found);
        self::assertSame('Alice', $found->name);
        self::assertSame(30, $found->age);
        self::assertSame('1', $found->getKey());
        self::assertSame($index, $found->getIndexName());
    }

    public function test_it_returns_null_when_the_document_is_missing(): void
    {
        // Arrange
        $this->createUserIndex('crud_missing');

        // Act
        $found = IntegrationUser::find('does-not-exist');

        // Assert
        self::assertNull($found);
    }

    public function test_find_or_fail_throws_for_a_missing_document(): void
    {
        // Arrange
        $this->createUserIndex('crud_fail');

        // Act / Assert
        $this->expectException(ModelNotFoundException::class);
        IntegrationUser::findOrFail('missing');
    }

    public function test_find_or_fail_returns_the_document_when_it_exists(): void
    {
        // Arrange
        $index = $this->createUserIndex('crud_found');
        $this->seedUsers($index, [['id' => '7', 'name' => 'Grace', 'age' => 42]]);

        // Act
        $found = IntegrationUser::findOrFail('7');

        // Assert
        self::assertSame('Grace', $found->name);
        self::assertSame(42, $found->age);
    }

    public function test_it_updates_a_document(): void
    {
        // Arrange
        $index = $this->createUserIndex('crud_update');
        $this->seedUsers($index, [['id' => '1', 'name' => 'Alice', 'age' => 30]]);
        $user = IntegrationUser::findOrFail('1');

        // Act
        $user->update(['name' => 'Alice Cooper', 'age' => 31]);
        $this->es('refresh', ['index' => $index]);

        // Assert
        $reloaded = IntegrationUser::findOrFail('1');
        self::assertSame('Alice Cooper', $reloaded->name);
        self::assertSame(31, $reloaded->age);
    }

    public function test_it_refreshes_the_model_from_elasticsearch(): void
    {
        // Arrange
        $index = $this->createUserIndex('crud_refresh');
        $this->seedUsers($index, [['id' => '1', 'name' => 'Alice', 'age' => 30]]);
        $user = IntegrationUser::findOrFail('1');

        // Act: mutate Elasticsearch out of band, then refresh the stale model.
        $this->es('update', [
            'index' => $index,
            'id' => '1',
            'body' => ['doc' => ['name' => 'Changed', 'age' => 99]],
        ]);
        $this->es('refresh', ['index' => $index]);
        $user->refresh();

        // Assert
        self::assertSame('Changed', $user->name);
        self::assertSame(99, $user->age);
    }

    public function test_it_deletes_a_document(): void
    {
        // Arrange
        $index = $this->createUserIndex('crud_delete');
        $this->seedUsers($index, [['id' => '1', 'name' => 'Alice']]);
        $user = IntegrationUser::findOrFail('1');

        // Act
        $deleted = $user->delete();
        $this->es('refresh', ['index' => $index]);

        // Assert
        self::assertTrue($deleted);
        self::assertNull(IntegrationUser::find('1'));

        $count = $this->es('count', ['index' => $index, 'body' => ['query' => ['match_all' => new stdClass()]]]);
        self::assertSame(0, $count['count']);
    }

    public function test_it_reads_typed_attributes_through_casts(): void
    {
        // Arrange
        $index = $this->createUserIndex('crud_cast');
        $this->seedUsers($index, [[
            'id' => '1',
            'name' => 'Alice',
            'age' => 30,
            'tags' => ['a', 'b'],
            'created_at' => '2024-01-02T03:04:05+00:00',
        ]]);

        // Act
        $user = IntegrationUser::findOrFail('1');

        // Assert
        self::assertSame(30, $user->age);
        self::assertSame(['a', 'b'], $user->tags);
        self::assertInstanceOf(DateTimeInterface::class, $user->created_at);
    }
}
