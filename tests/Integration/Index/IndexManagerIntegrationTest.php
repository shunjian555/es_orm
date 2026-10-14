<?php

declare(strict_types=1);

namespace EsOrm\Tests\Integration\Index;

use EsOrm\Index\IndexManager;
use EsOrm\Tests\Integration\IntegrationHelpers;
use EsOrm\Tests\Integration\IntegrationTestCase;

/**
 * Real index + mapping lifecycle against a live Elasticsearch node.
 */
final class IndexManagerIntegrationTest extends IntegrationTestCase
{
    use IntegrationHelpers;

    private function indices(): IndexManager
    {
        return IndexManager::on($this->manager);
    }

    public function test_it_creates_a_real_index_and_reports_its_existence(): void
    {
        // Arrange
        $index = $this->uniqueIndex('idx_create');
        $mapping = (new \EsOrm\Tests\Fixtures\IntegrationUser())->mappingDefinition();

        // Act
        $created = $this->indices()->createIndex($index, $mapping, ['number_of_replicas' => 0]);

        // Assert
        self::assertTrue($created);
        self::assertTrue($this->indices()->indexExists($index));
    }

    public function test_it_returns_false_when_creating_an_existing_index(): void
    {
        // Arrange
        $index = $this->uniqueIndex('idx_create_dupe');
        $indices = $this->indices();
        $indices->createIndex($index, null, ['number_of_replicas' => 0]);

        // Act
        $createdAgain = $indices->createIndex($index, null, ['number_of_replicas' => 0]);

        // Assert
        self::assertFalse($createdAgain);
    }

    public function test_it_persists_and_reads_back_the_declared_mapping(): void
    {
        // Arrange
        $index = $this->uniqueIndex('idx_mapping');
        $this->indices()->createIndex(
            $index,
            (new \EsOrm\Tests\Fixtures\IntegrationUser())->mappingDefinition(),
            ['number_of_replicas' => 0],
        );

        // Act
        $mapping = $this->indices()->getMapping($index);

        // Assert
        self::assertArrayHasKey($index, $mapping);
        $properties = $mapping[$index]['mappings']['properties'];
        self::assertArrayHasKey('name', $properties);
        self::assertSame('text', $properties['name']['type']);
        self::assertSame('keyword', $properties['name']['fields']['keyword']['type']);
        self::assertSame('nested', $properties['profile']['type']);
    }

    public function test_it_adds_new_fields_through_put_mapping(): void
    {
        // Arrange
        $index = $this->uniqueIndex('idx_put_mapping');
        $this->indices()->createIndex($index, null, ['number_of_replicas' => 0]);

        // Act
        $this->indices()->putMapping($index, [
            'properties' => ['nickname' => ['type' => 'keyword']],
        ]);
        $mapping = $this->indices()->getMapping($index);

        // Assert
        self::assertSame('keyword', $mapping[$index]['mappings']['properties']['nickname']['type']);
    }

    public function test_it_refreshes_and_deletes_the_index(): void
    {
        // Arrange
        $index = $this->uniqueIndex('idx_delete');
        $this->indices()->createIndex($index, null, ['number_of_replicas' => 0]);
        $this->es('index', ['index' => $index, 'id' => '1', 'body' => ['name' => 'Alice']]);

        // Act
        $refreshed = $this->indices()->refresh($index);

        // Assert
        self::assertTrue($refreshed);

        // Act
        $deleted = $this->indices()->deleteIndex($index);

        // Assert
        self::assertTrue($deleted);
        self::assertFalse($this->indices()->indexExists($index));
    }

    public function test_it_closes_and_reopens_the_index(): void
    {
        // Arrange
        $index = $this->uniqueIndex('idx_close');
        $indices = $this->indices();
        $indices->createIndex($index, null, ['number_of_replicas' => 0]);

        // Act
        $closed = $indices->close($index);

        // Assert
        self::assertTrue($closed);

        // Act
        $opened = $indices->open($index);

        // Assert
        self::assertTrue($opened);
        self::assertTrue($indices->indexExists($index));
    }
}
