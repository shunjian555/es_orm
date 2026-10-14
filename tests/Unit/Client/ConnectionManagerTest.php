<?php

declare(strict_types=1);

namespace EsOrm\Tests\Unit\Client;

use EsOrm\Client\Connection;
use EsOrm\Client\ConnectionManager;
use EsOrm\Contracts\ConnectionInterface;
use EsOrm\Exceptions\ConnectionException;
use EsOrm\Support\QueryLog;
use EsOrm\Support\QueryLogEntry;
use EsOrm\Tests\Support\FakeClient;
use EsOrm\Tests\TestCase;

final class ConnectionManagerTest extends TestCase
{
    public function test_it_resolves_and_caches_the_default_connection(): void
    {
        $first = $this->manager->connection();
        $second = $this->manager->connection();

        self::assertSame($first, $second);
        self::assertSame('default', $first->getName());
    }

    public function test_it_defaults_the_connection_name_to_default(): void
    {
        $manager = ConnectionManager::fromArray([
            'connections' => ['default' => ['client' => new FakeClient()]],
        ]);

        self::assertSame('default', $manager->getDefaultConnection());
    }

    public function test_it_reads_and_changes_the_default_connection(): void
    {
        $this->manager->setDefaultConnection('logs');

        self::assertSame('logs', $this->manager->getDefaultConnection());
    }

    public function test_it_resolves_a_named_connection_with_its_own_client(): void
    {
        $logsClient = new FakeClient();
        $manager = ConnectionManager::fromArray([
            'default' => 'default',
            'connections' => [
                'default' => ['client' => $this->client],
                'logs' => ['client' => $logsClient],
            ],
        ]);

        $connection = $manager->connection('logs');

        self::assertSame('logs', $connection->getName());
        self::assertSame($logsClient, $connection->getClient());
    }

    public function test_it_throws_for_an_unknown_connection(): void
    {
        $this->expectException(ConnectionException::class);
        $this->expectExceptionMessage('Elasticsearch connection [missing] is not configured.');

        $this->manager->connection('missing');
    }

    public function test_it_reports_whether_a_connection_exists(): void
    {
        self::assertTrue($this->manager->hasConnection('default'));
        self::assertFalse($this->manager->hasConnection('missing'));
    }

    public function test_it_adds_a_new_connection(): void
    {
        $this->manager->addConnection('extra', ['client' => new FakeClient()]);

        self::assertTrue($this->manager->hasConnection('extra'));
        self::assertSame('extra', $this->manager->connection('extra')->getName());
    }

    public function test_it_forces_a_prebuilt_connection_instance(): void
    {
        $connection = new Connection('forced', new FakeClient());

        $this->manager->setConnection('forced', $connection);

        self::assertSame($connection, $this->manager->connection('forced'));
    }

    public function test_it_rebuilds_a_connection_after_forgetting_it(): void
    {
        $first = $this->manager->connection();
        $this->manager->forget('default');
        $second = $this->manager->connection();

        self::assertNotSame($first, $second);
        self::assertInstanceOf(ConnectionInterface::class, $second);
    }

    public function test_it_resolves_lazily_through_a_custom_resolver(): void
    {
        $expected = new Connection('lazy', new FakeClient());
        $this->manager->extend('lazy', static fn (string $name): ConnectionInterface => $expected);

        $first = $this->manager->connection('lazy');

        self::assertSame($expected, $first);
        self::assertSame($first, $this->manager->connection('lazy'));
    }

    public function test_it_merges_the_global_config_into_a_connection(): void
    {
        $manager = ConnectionManager::fromArray([
            'default' => 'default',
            'timeout' => 5,
            'connections' => ['default' => ['client' => $this->client]],
        ]);

        self::assertSame(
            ['timeout' => 5, 'client' => $this->client],
            $manager->getConnectionConfig('default'),
        );
    }

    public function test_it_returns_an_empty_config_for_an_unknown_connection(): void
    {
        self::assertSame([], $this->manager->getConnectionConfig('missing'));
    }

    public function test_it_exposes_the_injected_query_log(): void
    {
        $log = new QueryLog();
        $manager = ConnectionManager::fromArray(['connections' => ['default' => ['client' => $this->client]]], $log);

        self::assertSame($log, $manager->queryLog());
    }

    public function test_it_can_replace_the_query_log(): void
    {
        $log = new QueryLog();
        $this->manager->setQueryLog($log);

        self::assertSame($log, $this->manager->queryLog());
    }

    public function test_connections_share_the_manager_query_log(): void
    {
        self::assertSame($this->manager->queryLog(), $this->manager->connection()->getQueryLog());
    }

    public function test_the_logging_config_disables_the_query_log(): void
    {
        $manager = ConnectionManager::fromArray([
            'logging' => ['enabled' => false],
            'connections' => ['default' => ['client' => $this->client]],
        ]);

        self::assertFalse($manager->queryLog()->isEnabled());
    }

    public function test_the_logging_config_caps_the_query_log_entries(): void
    {
        $manager = ConnectionManager::fromArray([
            'logging' => ['max_entries' => 2],
            'connections' => ['default' => ['client' => $this->client]],
        ]);

        $log = $manager->queryLog();
        $log->record($this->entry('first'));
        $log->record($this->entry('second'));
        $log->record($this->entry('third'));

        self::assertSame(2, $log->count());
        self::assertSame('third', $log->last()?->operation);
    }

    public function test_it_builds_a_manager_from_a_config_file(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'esorm_mgr_');
        file_put_contents($path, '<?php return ["default" => "primary", "connections" => ["primary" => ["hosts" => ["http://localhost:9200"]]]];');

        try {
            $manager = ConnectionManager::fromFile($path);
        } finally {
            unlink($path);
        }

        self::assertSame('primary', $manager->getDefaultConnection());
        self::assertTrue($manager->hasConnection('primary'));
    }

    private function entry(string $operation): QueryLogEntry
    {
        return new QueryLogEntry(operation: $operation, method: 'POST', endpoint: '/_search');
    }
}
