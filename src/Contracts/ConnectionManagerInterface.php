<?php

declare(strict_types=1);

namespace EsOrm\Contracts;

/**
 * Resolves and caches named connections.
 */
interface ConnectionManagerInterface
{
    /**
     * Resolve a connection by name (or the configured default one).
     */
    public function connection(?string $name = null): ConnectionInterface;

    /**
     * The name of the default connection.
     */
    public function getDefaultConnection(): string;

    /**
     * Change the default connection at runtime.
     */
    public function setDefaultConnection(string $name): void;

    /**
     * Register a brand new connection configuration.
     *
     * @param array<string, mixed> $config
     */
    public function addConnection(string $name, array $config): void;

    /**
     * Register a lazy factory for a connection. The factory receives the
     * connection name and must return a {@see ConnectionInterface}.
     *
     * @param callable(string): ConnectionInterface $resolver
     */
    public function extend(string $name, callable $resolver): void;

    /**
     * Force a fully built connection instance (used by tests and containers).
     */
    public function setConnection(string $name, ConnectionInterface $connection): void;

    /**
     * Whether a connection is configured or has already been resolved.
     */
    public function hasConnection(string $name): bool;

    /**
     * Drop a resolved connection so it is rebuilt on next access.
     */
    public function forget(string $name): void;
}
