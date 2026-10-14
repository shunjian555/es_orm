<?php

declare(strict_types=1);

namespace EsOrm\Tests\Integration\Index;

use EsOrm\Exceptions\IndexException;
use EsOrm\Index\AliasManager;
use EsOrm\Tests\Integration\IntegrationHelpers;
use EsOrm\Tests\Integration\IntegrationTestCase;

/**
 * Real alias creation and *atomic* switching against a live node.
 */
final class AliasIntegrationTest extends IntegrationTestCase
{
    use IntegrationHelpers;

    private function aliases(): AliasManager
    {
        return AliasManager::on($this->manager);
    }

    private function uniqueAlias(string $hint): string
    {
        return $this->prefix . 'alias_' . $hint . '_' . bin2hex(random_bytes(4));
    }

    public function test_it_creates_an_alias_that_resolves_to_its_index(): void
    {
        // Arrange
        $index = $this->createUserIndex('alias_create');
        $this->seedUsers($index, [
            ['id' => '1', 'name' => 'Alice'],
            ['id' => '2', 'name' => 'Bob'],
        ]);
        $alias = $this->uniqueAlias('create');

        // Act
        $created = $this->aliases()->createAlias($index, $alias);

        // Assert
        self::assertTrue($created);
        $resolution = $this->es('getAlias', ['name' => $alias]);
        self::assertSame([$index], array_keys($resolution));

        $hits = $this->rawMatchAll($alias);
        self::assertCount(2, $hits['hits']['hits']);
    }

    public function test_it_atomically_switches_an_alias_to_a_new_index(): void
    {
        // Arrange: the "old" index holds two documents.
        $old = $this->createUserIndex('alias_switch_old');
        $this->seedUsers($old, [
            ['id' => '1', 'name' => 'Alice'],
            ['id' => '2', 'name' => 'Bob'],
        ]);
        $alias = $this->uniqueAlias('switch');
        $this->aliases()->createAlias($old, $alias);

        // The "new" index holds a single, different document.
        $new = $this->createUserIndex('alias_switch_new');
        $this->seedUsers($new, [
            ['id' => '3', 'name' => 'Carol'],
        ]);

        // Assert the alias still points at the old index before the switch.
        self::assertSame([$old], array_keys($this->es('getAlias', ['name' => $alias])));
        self::assertCount(2, $this->rawMatchAll($alias)['hits']['hits']);

        // Act: atomically move the alias to the new index.
        $switched = $this->aliases()->switchAlias($alias, $new);

        // Assert: the alias now resolves to *exactly* the new index...
        self::assertTrue($switched);
        self::assertSame([$new], array_keys($this->es('getAlias', ['name' => $alias])));

        // ...and searches through the alias hit only the new index's document.
        $hits = $this->rawMatchAll($alias)['hits']['hits'];
        self::assertCount(1, $hits);
        self::assertSame($new, $hits[0]['_index']);
        self::assertSame('Carol', $hits[0]['_source']['name']);
    }

    public function test_it_deletes_an_alias(): void
    {
        // Arrange
        $index = $this->createUserIndex('alias_delete');
        $alias = $this->uniqueAlias('delete');
        $this->aliases()->createAlias($index, $alias);

        // Act
        $deleted = $this->aliases()->deleteAlias($index, $alias);

        // Assert
        self::assertTrue($deleted);

        $this->expectException(IndexException::class);
        $this->es('getAlias', ['name' => $alias]);
    }
}
