<?php

declare(strict_types=1);

namespace EsOrm\Tests\Unit\Laravel;

use EsOrm\Client\ConnectionManager;
use EsOrm\Index\AliasManager;
use EsOrm\Index\IndexManager;
use EsOrm\Index\ReindexManager;
use EsOrm\Laravel\EsManager;
use EsOrm\Migration\Migrator;
use EsOrm\Tests\Support\FakeClient;
use PHPUnit\Framework\TestCase as BaseTestCase;
use stdClass;

/**
 * The Laravel entry point is framework agnostic at its core: it only needs a
 * connection manager, so the wiring can be verified without a booted app.
 */
final class EsManagerTest extends BaseTestCase
{
    private ConnectionManager $manager;

    private FakeClient $client;

    private EsManager $es;

    protected function setUp(): void
    {
        parent::setUp();

        $this->client = new FakeClient();
        $this->manager = new ConnectionManager([
            'default' => 'default',
            'connections' => [
                'default' => ['client' => $this->client],
                'logs' => ['client' => $this->client],
            ],
        ]);
        $this->es = new EsManager($this->manager, 'database/es-migrations', 'es-migrations.json');
    }

    public function test_it_exposes_the_connection_manager(): void
    {
        // Assert
        self::assertSame($this->manager, $this->es->connections());
    }

    public function test_it_resolves_the_default_and_named_connections(): void
    {
        // Assert
        self::assertSame($this->manager->connection(), $this->es->connection());
        self::assertSame($this->manager->connection('logs'), $this->es->connection('logs'));
    }

    public function test_it_exposes_the_raw_client_of_a_connection(): void
    {
        // Assert
        self::assertSame($this->client, $this->es->client());
    }

    public function test_it_builds_managers_bound_to_a_connection(): void
    {
        // Assert
        self::assertInstanceOf(IndexManager::class, $this->es->index());
        self::assertInstanceOf(AliasManager::class, $this->es->alias('logs'));
        self::assertInstanceOf(ReindexManager::class, $this->es->reindex());
    }

    public function test_it_builds_the_migrator_with_the_configured_paths(): void
    {
        // Act
        $migrator = $this->es->migrator();

        // Assert
        self::assertInstanceOf(Migrator::class, $migrator);
    }

    public function test_search_forwards_to_the_connection(): void
    {
        // Arrange
        $params = ['index' => 'users', 'body' => ['query' => ['match_all' => new stdClass()]]];

        // Act
        $response = $this->es->search($params);

        // Assert
        self::assertSame($params, $this->client->lastParams('search'));
        self::assertArrayHasKey('hits', $response);
    }
}
