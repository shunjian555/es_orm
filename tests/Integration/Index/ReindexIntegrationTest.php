<?php

declare(strict_types=1);

namespace EsOrm\Tests\Integration\Index;

use EsOrm\Index\ReindexManager;
use EsOrm\Tests\Integration\IntegrationHelpers;
use EsOrm\Tests\Integration\IntegrationTestCase;
use stdClass;

/**
 * Real `_reindex` copies between two live indices.
 */
final class ReindexIntegrationTest extends IntegrationTestCase
{
    use IntegrationHelpers;

    private function reindex(): ReindexManager
    {
        return ReindexManager::on($this->manager);
    }

    private function countDocuments(string $index): int
    {
        $response = $this->es('count', [
            'index' => $index,
            'body' => ['query' => ['match_all' => new stdClass()]],
        ]);

        return (int) $response['count'];
    }

    public function test_it_reindexes_all_documents_and_the_count_matches(): void
    {
        // Arrange
        $source = $this->createUserIndex('reindex_src');
        $this->seedUsers($source, [
            ['id' => '1', 'name' => 'Alice', 'age' => 30],
            ['id' => '2', 'name' => 'Bob', 'age' => 25],
            ['id' => '3', 'name' => 'Carol', 'age' => 40],
        ]);
        $destination = $this->createUserIndex('reindex_dest');

        // Act
        $result = $this->reindex()->reindexTo($source, $destination, ['refresh' => true]);

        // Assert
        self::assertSame(3, (int) $result['created']);
        self::assertSame(3, $this->countDocuments($destination));

        // The documents really moved (ids preserved).
        $hits = $this->rawMatchAll($destination)['hits']['hits'];
        $ids = array_map(static fn (array $hit): string => $hit['_id'], $hits);
        sort($ids);
        self::assertSame(['1', '2', '3'], $ids);
    }

    public function test_it_reindexes_only_documents_matching_a_query(): void
    {
        // Arrange
        $source = $this->createUserIndex('reindex_query_src');
        $this->seedUsers($source, [
            ['id' => '1', 'name' => 'Alice', 'status' => 'active'],
            ['id' => '2', 'name' => 'Bob', 'status' => 'inactive'],
            ['id' => '3', 'name' => 'Carol', 'status' => 'active'],
        ]);
        $destination = $this->createUserIndex('reindex_query_dest');

        // Act
        $result = $this->reindex()->reindexTo($source, $destination, [
            'query' => ['term' => ['status' => 'active']],
            'batch_size' => 1,
            'refresh' => true,
        ]);

        // Assert
        self::assertSame(2, (int) $result['created']);
        self::assertSame(2, $this->countDocuments($destination));
    }
}
