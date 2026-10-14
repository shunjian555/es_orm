<?php

declare(strict_types=1);

namespace EsOrm\Tests\Unit\Index;

use EsOrm\Index\IndexManager;
use EsOrm\Mapping\Mapping;
use EsOrm\Tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

final class IndexManagerTest extends TestCase
{
    public function test_create_index_sends_the_mapping_body(): void
    {
        // Arrange
        $mapping = Mapping::create('articles')->keyword('status');
        $indices = IndexManager::on($this->manager);

        // Act
        $result = $indices->createIndex('articles', $mapping);

        // Assert
        self::assertTrue($result);
        self::assertSame([
            'index' => 'articles',
            'body' => ['mappings' => ['properties' => ['status' => ['type' => 'keyword']]]],
        ], $this->client->lastParams('createIndex'));
    }

    public function test_create_index_merges_extra_settings_into_the_body(): void
    {
        // Arrange
        $mapping = Mapping::create('articles')->keyword('status');
        $indices = IndexManager::on($this->manager);

        // Act
        $indices->createIndex('articles', $mapping, ['number_of_shards' => 3]);

        // Assert
        self::assertSame(
            ['number_of_shards' => 3],
            $this->client->lastParams('createIndex')['body']['settings'],
        );
    }

    public function test_create_index_without_a_mapping_sends_only_the_index(): void
    {
        // Arrange
        $indices = IndexManager::on($this->manager);

        // Act
        $indices->createIndex('articles');

        // Assert
        self::assertSame(['index' => 'articles'], $this->client->lastParams('createIndex'));
    }

    public function test_create_index_merges_raw_options(): void
    {
        // Arrange
        $indices = IndexManager::on($this->manager);

        // Act
        $indices->createIndex('articles', null, [], ['wait_for_active_shards' => 2]);

        // Assert
        self::assertSame([
            'index' => 'articles',
            'wait_for_active_shards' => 2,
        ], $this->client->lastParams('createIndex'));
    }

    public function test_create_index_is_a_no_op_when_the_index_exists(): void
    {
        // Arrange
        $this->client->setResponse('indexExists', true);
        $indices = IndexManager::on($this->manager);

        // Act
        $result = $indices->createIndex('articles');

        // Assert
        self::assertFalse($result);
        self::assertSame(0, $this->client->callCount('createIndex'));
    }

    public function test_delete_index_sends_the_index(): void
    {
        // Arrange
        $this->client->setResponse('indexExists', true);
        $indices = IndexManager::on($this->manager);

        // Act
        $result = $indices->deleteIndex('articles');

        // Assert
        self::assertTrue($result);
        self::assertSame(['index' => 'articles'], $this->client->lastParams('deleteIndex'));
    }

    public function test_delete_index_is_a_no_op_when_the_index_is_missing(): void
    {
        // Arrange
        $indices = IndexManager::on($this->manager);

        // Act
        $result = $indices->deleteIndex('articles');

        // Assert
        self::assertFalse($result);
        self::assertSame(0, $this->client->callCount('deleteIndex'));
    }

    public function test_index_exists_returns_the_client_result(): void
    {
        // Arrange
        $this->client->setResponse('indexExists', true);
        $indices = IndexManager::on($this->manager);

        // Act
        $result = $indices->indexExists('articles');

        // Assert
        self::assertTrue($result);
        self::assertSame(['index' => 'articles'], $this->client->lastParams('indexExists'));
    }

    public function test_get_index_sends_the_index_and_returns_the_response(): void
    {
        // Arrange
        $this->client->setResponse('getIndex', ['articles' => ['settings' => []]]);
        $indices = IndexManager::on($this->manager);

        // Act
        $response = $indices->getIndex('articles');

        // Assert
        self::assertSame(['index' => 'articles'], $this->client->lastParams('getIndex'));
        self::assertSame(['articles' => ['settings' => []]], $response);
    }

    public function test_get_mapping_sends_the_index(): void
    {
        // Arrange
        $indices = IndexManager::on($this->manager);

        // Act
        $indices->getMapping('articles');

        // Assert
        self::assertSame(['index' => 'articles'], $this->client->lastParams('getMapping'));
    }

    public function test_put_mapping_accepts_a_mapping_object(): void
    {
        // Arrange
        $mapping = Mapping::create('articles')->keyword('status');
        $indices = IndexManager::on($this->manager);

        // Act
        $result = $indices->putMapping('articles', $mapping);

        // Assert
        self::assertTrue($result);
        self::assertSame([
            'index' => 'articles',
            'body' => ['properties' => ['status' => ['type' => 'keyword']]],
        ], $this->client->lastParams('putMapping'));
    }

    public function test_put_mapping_accepts_a_raw_array(): void
    {
        // Arrange
        $indices = IndexManager::on($this->manager);

        // Act
        $indices->putMapping('articles', ['properties' => ['title' => ['type' => 'text']]]);

        // Assert
        self::assertSame([
            'index' => 'articles',
            'body' => ['properties' => ['title' => ['type' => 'text']]],
        ], $this->client->lastParams('putMapping'));
    }

    public function test_force_merge_merges_the_options(): void
    {
        // Arrange
        $indices = IndexManager::on($this->manager);

        // Act
        $result = $indices->forceMerge('articles', ['max_num_segments' => 1]);

        // Assert
        self::assertTrue($result);
        self::assertSame(
            ['index' => 'articles', 'max_num_segments' => 1],
            $this->client->lastParams('forceMerge'),
        );
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function simpleOperationProvider(): array
    {
        return [
            'refresh' => ['refresh', 'refresh'],
            'flush' => ['flush', 'flush'],
            'close' => ['close', 'closeIndex'],
            'open' => ['open', 'openIndex'],
        ];
    }

    #[DataProvider('simpleOperationProvider')]
    public function test_simple_index_operations_send_only_the_index(string $method, string $operation): void
    {
        // Arrange
        $indices = IndexManager::on($this->manager);

        // Act
        $result = $indices->{$method}('articles');

        // Assert
        self::assertTrue($result);
        self::assertSame(['index' => 'articles'], $this->client->lastParams($operation));
    }
}
