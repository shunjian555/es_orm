<?php

declare(strict_types=1);

namespace EsOrm\Laravel;

use EsOrm\Contracts\ClientInterface;
use EsOrm\Contracts\ConnectionInterface;
use EsOrm\Contracts\ConnectionManagerInterface;
use EsOrm\Index\AliasManager;
use EsOrm\Index\IndexManager;
use EsOrm\Index\ReindexManager;
use EsOrm\Migration\Migrator;

/**
 * The Laravel integration entry point behind the `Es` facade.
 *
 * It wires the framework agnostic core (connection manager, index manager, ...)
 * into the Laravel container while keeping the core package itself free of any
 * Laravel dependency.
 *
 * ```php
 * Es::connection();          // the default connection
 * Es::connection('logs');    // a named connection
 * Es::index();               // an IndexManager
 * Es::search([...]);         // a raw search
 * ```
 */
final class EsManager
{
    public function __construct(
        private readonly ConnectionManagerInterface $connections,
        private readonly string $migrationsPath = 'database/es-migrations',
        private readonly string $migrationRepository = 'es-orm-migrations.json',
    ) {
    }

    /**
     * The underlying connection manager.
     */
    public function connections(): ConnectionManagerInterface
    {
        return $this->connections;
    }

    /**
     * Resolve a connection by name (or the configured default one).
     */
    public function connection(?string $name = null): ConnectionInterface
    {
        return $this->connections->connection($name);
    }

    /**
     * The raw client of a connection.
     */
    public function client(?string $name = null): ClientInterface
    {
        return $this->connection($name)->getClient();
    }

    /**
     * An index manager bound to a connection.
     */
    public function index(?string $name = null): IndexManager
    {
        return IndexManager::on($this->connections, $name);
    }

    /**
     * An alias manager bound to a connection.
     */
    public function alias(?string $name = null): AliasManager
    {
        return AliasManager::on($this->connections, $name);
    }

    /**
     * A reindex manager bound to a connection.
     */
    public function reindex(?string $name = null): ReindexManager
    {
        return ReindexManager::on($this->connections, $name);
    }

    /**
     * The index migration runner.
     */
    public function migrator(): Migrator
    {
        return new Migrator($this->index(), $this->migrationsPath, $this->migrationRepository);
    }

    /**
     * Run a raw search request and return the raw Elasticsearch response.
     *
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    public function search(array $params, ?string $name = null): array
    {
        return $this->connection($name)->search($params);
    }
}
