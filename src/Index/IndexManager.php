<?php

declare(strict_types=1);

namespace EsOrm\Index;

use EsOrm\Contracts\ConnectionInterface;
use EsOrm\Contracts\ConnectionManagerInterface;
use EsOrm\Contracts\IndexManagerInterface;
use EsOrm\Contracts\MappingInterface;

/**
 * Creates and administers Elasticsearch indices.
 *
 * The manager is a thin, typed façade over the index level Elasticsearch APIs;
 * it reuses the {@see ConnectionInterface} so retry, logging and exception
 * mapping stay centralised.
 *
 * ```php
 * $indices = IndexManager::on($connectionManager);
 *
 * $indices->createIndex('articles', $mapping);
 * $indices->putMapping('articles', $extraMapping);
 * $indices->refresh('articles');
 * ```
 */
final class IndexManager implements IndexManagerInterface
{
    public function __construct(private readonly ConnectionInterface $connection)
    {
    }

    /**
     * Build a manager bound to a named connection.
     */
    public static function on(ConnectionManagerInterface $manager, ?string $connection = null): self
    {
        return new self($manager->connection($connection));
    }

    public function createIndex(string $index, ?MappingInterface $mapping = null, array $settings = [], array $options = []): bool
    {
        if ($this->indexExists($index)) {
            return false;
        }

        $body = $mapping instanceof MappingInterface ? $mapping->toIndexBody() : [];

        if ($settings !== []) {
            /** @var array<string, mixed> $existing */
            $existing = is_array($body['settings'] ?? null) ? $body['settings'] : [];
            $body['settings'] = array_replace($existing, $settings);
        }

        $params = ['index' => $index];

        if ($body !== []) {
            $params['body'] = $body;
        }

        $this->connection->request('createIndex', array_replace($params, $options));

        return true;
    }

    public function deleteIndex(string $index): bool
    {
        if (!$this->indexExists($index)) {
            return false;
        }

        $this->connection->request('deleteIndex', ['index' => $index]);

        return true;
    }

    public function indexExists(string $index): bool
    {
        return $this->connection->getClient()->indexExists(['index' => $index]);
    }

    public function getIndex(string $index): array
    {
        return $this->connection->request('getIndex', ['index' => $index]);
    }

    public function getMapping(string $index): array
    {
        return $this->connection->request('getMapping', ['index' => $index]);
    }

    public function putMapping(string $index, MappingInterface|array $mapping): bool
    {
        $body = $mapping instanceof MappingInterface ? $mapping->toArray() : $mapping;

        $this->connection->request('putMapping', ['index' => $index, 'body' => $body]);

        return true;
    }

    public function refresh(string $index): bool
    {
        $this->connection->request('refresh', ['index' => $index]);

        return true;
    }

    public function flush(string $index): bool
    {
        $this->connection->request('flush', ['index' => $index]);

        return true;
    }

    public function forceMerge(string $index, array $options = []): bool
    {
        $this->connection->request('forceMerge', array_replace(['index' => $index], $options));

        return true;
    }

    public function close(string $index): bool
    {
        $this->connection->request('closeIndex', ['index' => $index]);

        return true;
    }

    public function open(string $index): bool
    {
        $this->connection->request('openIndex', ['index' => $index]);

        return true;
    }
}
