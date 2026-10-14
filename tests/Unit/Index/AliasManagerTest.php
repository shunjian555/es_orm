<?php

declare(strict_types=1);

namespace EsOrm\Tests\Unit\Index;

use EsOrm\Exceptions\IndexException;
use EsOrm\Index\AliasManager;
use EsOrm\Tests\TestCase;

final class AliasManagerTest extends TestCase
{
    public function test_create_alias_sends_a_single_add_action(): void
    {
        // Arrange
        $aliases = AliasManager::on($this->manager);

        // Act
        $result = $aliases->createAlias('articles_v1', 'articles');

        // Assert
        self::assertTrue($result);
        self::assertSame([
            'body' => [
                'actions' => [
                    ['add' => ['index' => 'articles_v1', 'alias' => 'articles']],
                ],
            ],
        ], $this->client->lastParams('updateAliases'));
    }

    public function test_create_alias_merges_extra_action_options(): void
    {
        // Arrange
        $aliases = AliasManager::on($this->manager);

        // Act
        $aliases->createAlias('articles_v1', 'articles', ['routing' => 'eu']);

        // Assert
        self::assertSame(
            [['add' => ['index' => 'articles_v1', 'alias' => 'articles', 'routing' => 'eu']]],
            $this->client->lastParams('updateAliases')['body']['actions'],
        );
    }

    public function test_delete_alias_sends_a_single_remove_action(): void
    {
        // Arrange
        $aliases = AliasManager::on($this->manager);

        // Act
        $result = $aliases->deleteAlias('articles_v1', 'articles');

        // Assert
        self::assertTrue($result);
        self::assertSame([
            'body' => [
                'actions' => [
                    ['remove' => ['index' => 'articles_v1', 'alias' => 'articles']],
                ],
            ],
        ], $this->client->lastParams('updateAliases'));
    }

    public function test_switch_alias_removes_the_source_and_adds_the_target(): void
    {
        // Arrange
        $aliases = AliasManager::on($this->manager);

        // Act
        $result = $aliases->switchAlias('articles', 'articles_v2', 'articles_v1');

        // Assert
        self::assertTrue($result);
        self::assertSame([
            'actions' => [
                ['remove' => ['index' => 'articles_v1', 'alias' => 'articles']],
                ['add' => ['index' => 'articles_v2', 'alias' => 'articles']],
            ],
        ], $this->client->lastParams('updateAliases')['body']);
    }

    public function test_switch_alias_skips_removing_the_target_index(): void
    {
        // Arrange
        $aliases = AliasManager::on($this->manager);

        // Act
        $aliases->switchAlias('articles', 'articles_v2', 'articles_v2');

        // Assert
        self::assertSame([
            'actions' => [
                ['add' => ['index' => 'articles_v2', 'alias' => 'articles']],
            ],
        ], $this->client->lastParams('updateAliases')['body']);
    }

    public function test_switch_alias_auto_detects_the_current_indices(): void
    {
        // Arrange
        $this->client->setResponse('getAlias', [
            'articles_v1' => ['aliases' => ['articles' => []]],
            'articles_v2' => ['aliases' => ['articles' => []]],
        ]);
        $aliases = AliasManager::on($this->manager);

        // Act
        $aliases->switchAlias('articles', 'articles_v3');

        // Assert
        self::assertSame(['name' => 'articles'], $this->client->lastParams('getAlias'));
        self::assertSame([
            'actions' => [
                ['remove' => ['index' => 'articles_v1', 'alias' => 'articles']],
                ['remove' => ['index' => 'articles_v2', 'alias' => 'articles']],
                ['add' => ['index' => 'articles_v3', 'alias' => 'articles']],
            ],
        ], $this->client->lastParams('updateAliases')['body']);
    }

    public function test_switch_alias_only_adds_when_the_alias_does_not_exist(): void
    {
        // Arrange
        $this->client->willThrow('getAlias', new IndexException('alias [articles] missing'));
        $aliases = AliasManager::on($this->manager);

        // Act
        $aliases->switchAlias('articles', 'articles_v1');

        // Assert
        self::assertSame([
            'actions' => [
                ['add' => ['index' => 'articles_v1', 'alias' => 'articles']],
            ],
        ], $this->client->lastParams('updateAliases')['body']);
    }

    public function test_get_alias_sends_the_index(): void
    {
        // Arrange
        $this->client->setResponse('getAlias', ['articles_v1' => ['aliases' => ['articles' => []]]]);
        $aliases = AliasManager::on($this->manager);

        // Act
        $response = $aliases->getAlias('articles_v1');

        // Assert
        self::assertSame(['index' => 'articles_v1'], $this->client->lastParams('getAlias'));
        self::assertSame(['articles_v1' => ['aliases' => ['articles' => []]]], $response);
    }
}
