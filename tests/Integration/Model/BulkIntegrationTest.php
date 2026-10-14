<?php

declare(strict_types=1);

namespace EsOrm\Tests\Integration\Model;

use EsOrm\Tests\Fixtures\IntegrationUser;
use EsOrm\Tests\Integration\IntegrationHelpers;
use EsOrm\Tests\Integration\IntegrationTestCase;
use stdClass;

/**
 * Real Bulk API + Multi Get against a live node.
 */
final class BulkIntegrationTest extends IntegrationTestCase
{
    use IntegrationHelpers;

    public function test_it_bulk_creates_documents(): void
    {
        // Arrange
        $index = $this->createUserIndex('bulk_create');

        // Act
        $created = IntegrationUser::bulkCreate([
            ['id' => '1', 'name' => 'Alice', 'age' => 30],
            ['id' => '2', 'name' => 'Bob', 'age' => 25],
            ['id' => '3', 'name' => 'Carol', 'age' => 40],
        ]);
        $this->es('refresh', ['index' => $index]);

        // Assert
        self::assertCount(3, $created);
        $count = $this->es('count', ['index' => $index, 'body' => ['query' => ['match_all' => new stdClass()]]]);
        self::assertSame(3, $count['count']);
        self::assertSame('Alice', IntegrationUser::findOrFail('1')->name);
    }

    public function test_it_bulk_updates_documents(): void
    {
        // Arrange
        $index = $this->createUserIndex('bulk_update');
        $this->seedUsers($index, [
            ['id' => '1', 'name' => 'Alice', 'age' => 30],
            ['id' => '2', 'name' => 'Bob', 'age' => 25],
        ]);

        // Act
        $affected = IntegrationUser::bulkUpdate([
            ['id' => '1', 'name' => 'Alice Updated'],
            ['id' => '2', 'name' => 'Bob Updated'],
        ]);
        $this->es('refresh', ['index' => $index]);

        // Assert
        self::assertSame(2, $affected);
        self::assertSame('Alice Updated', IntegrationUser::findOrFail('1')->name);
        self::assertSame('Bob Updated', IntegrationUser::findOrFail('2')->name);
        self::assertSame(30, IntegrationUser::findOrFail('1')->age);
    }

    public function test_it_bulk_deletes_documents(): void
    {
        // Arrange
        $index = $this->createUserIndex('bulk_delete');
        $this->seedUsers($index, [
            ['id' => '1', 'name' => 'Alice'],
            ['id' => '2', 'name' => 'Bob'],
            ['id' => '3', 'name' => 'Carol'],
        ]);

        // Act
        $affected = IntegrationUser::bulkDelete(['1', '3']);
        $this->es('refresh', ['index' => $index]);

        // Assert
        self::assertSame(2, $affected);
        self::assertNull(IntegrationUser::find('1'));
        self::assertNull(IntegrationUser::find('3'));
        self::assertSame('Bob', IntegrationUser::findOrFail('2')->name);
    }

    public function test_it_multi_gets_documents_by_id(): void
    {
        // Arrange
        $index = $this->createUserIndex('bulk_mget');
        $this->seedUsers($index, [
            ['id' => '1', 'name' => 'Alice', 'age' => 30],
            ['id' => '2', 'name' => 'Bob', 'age' => 25],
            ['id' => '3', 'name' => 'Carol', 'age' => 40],
        ]);

        // Act: '99' does not exist.
        $found = IntegrationUser::mget(['1', '3', '99']);

        // Assert
        self::assertCount(2, $found);
        self::assertSame('1', $found->first()->getKey());
        self::assertSame('Alice', $found->first()->name);
        self::assertSame('Carol', $found->last()->name);
    }
}
