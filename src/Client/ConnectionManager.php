<?php

declare(strict_types=1);

namespace EsOrm\Client;

use Elastic\Elasticsearch\Client;
use EsOrm\Contracts\ClientInterface;
use EsOrm\Contracts\ConnectionInterface;
use EsOrm\Contracts\ConnectionManagerInterface;
use EsOrm\Exceptions\ConnectionException;
use EsOrm\Support\Arr;
use EsOrm\Support\Config;
use EsOrm\Support\QueryLog;
use Psr\Log\LoggerInterface;

/**
 * Resolves and caches named Elasticsearch connections.
 *
 * The manager is framework agnostic: give it the `es-orm` config array (or a
 * {@see Config} instance) and it will build lazily created connections on
 * demand. Global keys such as `timeout`, `retry` and `logging` are merged into
 * every connection so they can be declared once at the top level.
 */
final class ConnectionManager implements ConnectionManagerInterface
{
    /**
     * @var array<string, array<string, mixed>>
     */
    private array $configs = [];

    /**
     * @var array<string, ConnectionInterface>
     */
    private array $connections = [];

    /**
     * @var array<string, callable(string): ConnectionInterface>
     */
    private array $resolvers = [];

    /**
     * Global configuration keys shared by every connection.
     *
     * @var array<string, mixed>
     */
    private array $global;

    private string $default;

    private QueryLog $queryLog;

    /**
     * @param array<string, mixed>|Config $config
     */
    public function __construct(
        array|Config $config = [],
        ?QueryLog $queryLog = null,
        private readonly ?LoggerInterface $logger = null,
    ) {
        $items = $config instanceof Config ? $config->all() : $config;

        $this->global = Arr::except($items, ['connections', 'default']);
        $connections = $items['connections'] ?? [];

        /** @var array<string, array<string, mixed>> $connections */
        $this->configs = is_array($connections) ? $connections : [];
        $this->default = is_string($items['default'] ?? null) ? $items['default'] : 'default';
        $this->queryLog = $queryLog ?? $this->makeQueryLog();
    }

    /**
     * @param array<string, mixed> $config
     */
    public static function fromArray(array $config, ?QueryLog $queryLog = null, ?LoggerInterface $logger = null): self
    {
        return new self($config, $queryLog, $logger);
    }

    public static function fromFile(string $path, ?QueryLog $queryLog = null, ?LoggerInterface $logger = null): self
    {
        return new self(Config::fromFile($path), $queryLog, $logger);
    }

    public function connection(?string $name = null): ConnectionInterface
    {
        $name ??= $this->default;

        if (isset($this->connections[$name])) {
            return $this->connections[$name];
        }

        if (isset($this->resolvers[$name])) {
            $resolver = $this->resolvers[$name];

            return $this->connections[$name] = $resolver($name);
        }

        $config = $this->configs[$name] ?? null;

        if (!is_array($config)) {
            throw new ConnectionException(sprintf('Elasticsearch connection [%s] is not configured.', $name));
        }

        return $this->connections[$name] = $this->createConnection($name, $config);
    }

    public function getDefaultConnection(): string
    {
        return $this->default;
    }

    public function setDefaultConnection(string $name): void
    {
        $this->default = $name;
    }

    public function addConnection(string $name, array $config): void
    {
        $this->configs[$name] = $config;
        unset($this->connections[$name]);
    }

    public function extend(string $name, callable $resolver): void
    {
        $this->resolvers[$name] = $resolver;
        unset($this->connections[$name]);
    }

    public function setConnection(string $name, ConnectionInterface $connection): void
    {
        $this->connections[$name] = $connection;
    }

    public function hasConnection(string $name): bool
    {
        return isset($this->connections[$name]) || isset($this->resolvers[$name]) || isset($this->configs[$name]);
    }

    public function forget(string $name): void
    {
        unset($this->connections[$name]);
    }

    /**
     * The shared query log (used by `dump()`/`dd()` and Laravel integration).
     */
    public function queryLog(): QueryLog
    {
        return $this->queryLog;
    }

    public function setQueryLog(QueryLog $queryLog): void
    {
        $this->queryLog = $queryLog;
    }

    /**
     * The effective configuration of a connection (global defaults merged).
     *
     * @return array<string, mixed>
     */
    public function getConnectionConfig(string $name): array
    {
        $config = $this->configs[$name] ?? [];

        return array_replace($this->global, $config);
    }

    /**
     * @param array<string, mixed> $config
     */
    private function createConnection(string $name, array $config): ConnectionInterface
    {
        $config = array_replace($this->global, $config);
        $client = $this->createClient($config);

        return new Connection(
            $name,
            $client,
            $config,
            RetryHandler::fromConfig((array) ($config['retry'] ?? [])),
            $this->queryLog,
            $this->logger,
        );
    }

    /**
     * @param array<string, mixed> $config
     */
    private function createClient(array $config): ClientInterface
    {
        $client = $config['client'] ?? null;

        if ($client instanceof ClientInterface) {
            return $client;
        }

        if (is_object($client) && $client instanceof Client) {
            return new ElasticsearchClient($client);
        }

        return ElasticsearchClient::fromConfig($config, $this->logger);
    }

    private function makeQueryLog(): QueryLog
    {
        $logging = $this->global['logging'] ?? [];
        $logging = is_array($logging) ? $logging : [];

        $queryLog = new QueryLog((int) ($logging['max_entries'] ?? 100));
        $queryLog->enable((bool) ($logging['enabled'] ?? true));

        return $queryLog;
    }
}
