<?php

declare(strict_types=1);

namespace EsOrm\Tests\Unit\Index;

use EsOrm\Index\ReindexManager;
use EsOrm\Tests\TestCase;

final class ReindexManagerTest extends TestCase
{
    public function test_reindex_to_sends_the_minimal_body(): void
    {
        // Arrange
        $this->client->setResponse('reindex', ['created' => 3, 'updated' => 0]);
        $reindex = ReindexManager::on($this->manager);

        // Act
        $result = $reindex->reindexTo('articles_v1', 'articles_v2');

        // Assert
        self::assertSame(['created' => 3, 'updated' => 0], $result);
        self::assertSame([
            'body' => [
                'source' => ['index' => 'articles_v1'],
                'dest' => ['index' => 'articles_v2'],
            ],
        ], $this->client->lastParams('reindex'));
    }

    public function test_reindex_to_adds_the_query_to_the_source(): void
    {
        // Arrange
        $reindex = ReindexManager::on($this->manager);

        // Act
        $reindex->reindexTo('articles_v1', 'articles_v2', [
            'query' => ['term' => ['status' => 'published']],
        ]);

        // Assert
        self::assertSame(
            ['term' => ['status' => 'published']],
            $this->client->lastParams('reindex')['body']['source']['query'],
        );
    }

    public function test_reindex_to_maps_batch_size_to_source_size(): void
    {
        // Arrange
        $reindex = ReindexManager::on($this->manager);

        // Act
        $reindex->reindexTo('articles_v1', 'articles_v2', ['batch_size' => 500]);

        // Assert
        self::assertSame(500, $this->client->lastParams('reindex')['body']['source']['size']);
    }

    public function test_reindex_to_accepts_size_directly(): void
    {
        // Arrange
        $reindex = ReindexManager::on($this->manager);

        // Act
        $reindex->reindexTo('articles_v1', 'articles_v2', ['size' => 250]);

        // Assert
        self::assertSame(250, $this->client->lastParams('reindex')['body']['source']['size']);
    }

    public function test_reindex_to_adds_source_filtering(): void
    {
        // Arrange
        $reindex = ReindexManager::on($this->manager);

        // Act
        $reindex->reindexTo('articles_v1', 'articles_v2', [
            '_source' => ['includes' => ['title', 'status']],
        ]);

        // Assert
        self::assertSame(
            ['includes' => ['title', 'status']],
            $this->client->lastParams('reindex')['body']['source']['_source'],
        );
    }

    public function test_reindex_to_adds_a_script(): void
    {
        // Arrange
        $reindex = ReindexManager::on($this->manager);

        // Act
        $reindex->reindexTo('articles_v1', 'articles_v2', [
            'script' => ['source' => 'ctx._source.migrated = true'],
        ]);

        // Assert
        self::assertSame(
            ['source' => 'ctx._source.migrated = true'],
            $this->client->lastParams('reindex')['body']['script'],
        );
    }

    public function test_reindex_to_adds_the_conflicts_policy(): void
    {
        // Arrange
        $reindex = ReindexManager::on($this->manager);

        // Act
        $reindex->reindexTo('articles_v1', 'articles_v2', ['conflicts' => 'proceed']);

        // Assert
        self::assertSame('proceed', $this->client->lastParams('reindex')['body']['conflicts']);
    }

    public function test_reindex_to_adds_max_docs(): void
    {
        // Arrange
        $reindex = ReindexManager::on($this->manager);

        // Act
        $reindex->reindexTo('articles_v1', 'articles_v2', ['max_docs' => 1000]);

        // Assert
        self::assertSame(1000, $this->client->lastParams('reindex')['body']['max_docs']);
    }

    public function test_reindex_to_puts_slices_refresh_and_wait_for_completion_on_the_params(): void
    {
        // Arrange
        $reindex = ReindexManager::on($this->manager);

        // Act
        $reindex->reindexTo('articles_v1', 'articles_v2', [
            'slices' => 4,
            'refresh' => true,
            'wait_for_completion' => false,
        ]);

        // Assert
        $params = $this->client->lastParams('reindex');
        self::assertSame(4, $params['slices']);
        self::assertTrue($params['refresh']);
        self::assertFalse($params['wait_for_completion']);
    }

    public function test_reindex_to_builds_the_full_options_body(): void
    {
        // Arrange
        $reindex = ReindexManager::on($this->manager);

        // Act
        $reindex->reindexTo('articles_v1', 'articles_v2', [
            'query' => ['term' => ['status' => 'published']],
            'batch_size' => 100,
            'script' => ['source' => 'ctx._source.x = 1'],
            'conflicts' => 'abort',
            'max_docs' => 50,
            'refresh' => true,
        ]);

        // Assert
        self::assertSame([
            'body' => [
                'source' => [
                    'index' => 'articles_v1',
                    'query' => ['term' => ['status' => 'published']],
                    'size' => 100,
                ],
                'dest' => ['index' => 'articles_v2'],
                'script' => ['source' => 'ctx._source.x = 1'],
                'conflicts' => 'abort',
                'max_docs' => 50,
            ],
            'refresh' => true,
        ], $this->client->lastParams('reindex'));
    }

    public function test_reindex_passes_raw_params_through(): void
    {
        // Arrange
        $reindex = ReindexManager::on($this->manager);

        // Act
        $reindex->reindex(['body' => ['source' => ['index' => 'a'], 'dest' => ['index' => 'b']]]);

        // Assert
        self::assertSame(
            ['body' => ['source' => ['index' => 'a'], 'dest' => ['index' => 'b']]],
            $this->client->lastParams('reindex'),
        );
    }
}
