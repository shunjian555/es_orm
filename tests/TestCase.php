<?php

declare(strict_types=1);

namespace EsOrm\Tests;

use EsOrm\Client\ConnectionManager;
use EsOrm\Contracts\ConnectionInterface;
use EsOrm\Model\Model;
use EsOrm\Query\Builder;
use EsOrm\Tests\Support\FakeClient;
use PHPUnit\Framework\TestCase as BaseTestCase;
use ReflectionProperty;

/**
 * Base class for the unit test suite.
 *
 * It wires a {@see FakeClient} into a fresh {@see ConnectionManager} so no real
 * Elasticsearch node is ever required, and it makes sure the static state
 * shared by {@see Model} (connection manager, event dispatcher, global scopes)
 * is isolated between tests.
 */
abstract class TestCase extends BaseTestCase
{
    protected FakeClient $client;

    protected ConnectionManager $manager;

    /**
     * @var array<string, mixed>
     */
    protected array $config = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->resetModelState();
        $this->client = new FakeClient();
        $this->manager = $this->makeManager($this->client, $this->config);
        Model::setConnectionManager($this->manager);
    }

    protected function tearDown(): void
    {
        $this->resetModelState();

        parent::tearDown();
    }

    /**
     * Build a connection manager exposing the given fake client on the
     * `default` connection, plus optional extra connections.
     *
     * @param array<string, mixed> $config Global configuration (pagination, bulk, ...)
     */
    protected function makeManager(FakeClient $client, array $config = []): ConnectionManager
    {
        return new ConnectionManager(array_replace([
            'default' => 'default',
            'connections' => [
                'default' => ['client' => $client],
            ],
        ], $config));
    }

    protected function connection(): ConnectionInterface
    {
        return $this->manager->connection();
    }

    /**
     * Create a query builder for the given model/query factory.
     *
     * @template T of Model
     * @param class-string<T> $model
     */
    protected function query(string $model): Builder
    {
        /** @var Builder $builder */
        $builder = $model::query();

        return $builder;
    }

    /**
     * Reset every piece of static state owned by {@see Model}.
     */
    protected function resetModelState(): void
    {
        Model::setConnectionManager(null);
        Model::setEventDispatcher(null);

        $scopes = new ReflectionProperty(Model::class, 'globalScopes');
        $scopes->setValue(null, []);
    }
}
