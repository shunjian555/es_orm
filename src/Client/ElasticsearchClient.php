<?php

declare(strict_types=1);

namespace EsOrm\Client;

use Elastic\Elasticsearch\Client;
use Elastic\Elasticsearch\Response\Elasticsearch;
use EsOrm\Contracts\ClientInterface;
use JsonSerializable;

/**
 * Thin adapter over the official Elasticsearch PHP client.
 *
 * Responsibilities:
 *  - implement the package owned {@see ClientInterface} so the ORM never
 *    depends on the official client API directly
 *  - normalise responses: the official client returns an object implementing
 *    {@see \Psr\Http\Message\ResponseInterface} plus `asArray()`/`asBool()`,
 *    while the ORM works with plain arrays
 */
final class ElasticsearchClient implements ClientInterface
{
    public function __construct(private readonly Client $client)
    {
    }

    /**
     * @param array<string, mixed> $config
     */
    public static function fromConfig(array $config, ?\Psr\Log\LoggerInterface $logger = null): self
    {
        ClientFactory::assertInstalled();

        return new self(ClientFactory::create($config, $logger));
    }

    /**
     * The wrapped official client, for advanced use cases.
     */
    public function client(): Client
    {
        return $this->client;
    }

    public function search(array $params): array
    {
        return $this->toArray($this->client->search($params));
    }

    public function count(array $params): array
    {
        return $this->toArray($this->client->count($params));
    }

    public function index(array $params): array
    {
        return $this->toArray($this->client->index($params));
    }

    public function get(array $params): array
    {
        return $this->toArray($this->client->get($params));
    }

    public function update(array $params): array
    {
        return $this->toArray($this->client->update($params));
    }

    public function delete(array $params): array
    {
        return $this->toArray($this->client->delete($params));
    }

    public function bulk(array $params): array
    {
        return $this->toArray($this->client->bulk($params));
    }

    public function mget(array $params): array
    {
        return $this->toArray($this->client->mget($params));
    }

    public function msearch(array $params): array
    {
        return $this->toArray($this->client->msearch($params));
    }

    public function scroll(array $params): array
    {
        return $this->toArray($this->client->scroll($params));
    }

    public function clearScroll(array $params): array
    {
        return $this->toArray($this->client->clearScroll($params));
    }

    public function openPointInTime(array $params): array
    {
        return $this->toArray($this->client->openPointInTime($params));
    }

    public function closePointInTime(array $params): array
    {
        return $this->toArray($this->client->closePointInTime($params));
    }

    public function reindex(array $params): array
    {
        return $this->toArray($this->client->reindex($params));
    }

    public function createIndex(array $params): array
    {
        return $this->toArray($this->client->indices()->create($params));
    }

    public function deleteIndex(array $params): array
    {
        return $this->toArray($this->client->indices()->delete($params));
    }

    public function indexExists(array $params): bool
    {
        return $this->toBool($this->client->indices()->exists($params));
    }

    public function getIndex(array $params): array
    {
        return $this->toArray($this->client->indices()->get($params));
    }

    public function getMapping(array $params): array
    {
        return $this->toArray($this->client->indices()->getMapping($params));
    }

    public function putMapping(array $params): array
    {
        return $this->toArray($this->client->indices()->putMapping($params));
    }

    public function refresh(array $params): array
    {
        return $this->toArray($this->client->indices()->refresh($params));
    }

    public function flush(array $params): array
    {
        return $this->toArray($this->client->indices()->flush($params));
    }

    public function forceMerge(array $params): array
    {
        return $this->toArray($this->client->indices()->forcemerge($params));
    }

    public function closeIndex(array $params): array
    {
        return $this->toArray($this->client->indices()->close($params));
    }

    public function openIndex(array $params): array
    {
        return $this->toArray($this->client->indices()->open($params));
    }

    public function updateAliases(array $params): array
    {
        return $this->toArray($this->client->indices()->updateAliases($params));
    }

    public function getAlias(array $params): array
    {
        return $this->toArray($this->client->indices()->getAlias($params));
    }

    /**
     * @return array<string, mixed>
     */
    private function toArray(mixed $response): array
    {
        if (is_array($response)) {
            return $response;
        }

        if ($response instanceof Elasticsearch) {
            return $response->asArray();
        }

        if ($response instanceof JsonSerializable) {
            $decoded = $response->jsonSerialize();

            return is_array($decoded) ? $decoded : [];
        }

        return [];
    }

    private function toBool(mixed $response): bool
    {
        if (is_bool($response)) {
            return $response;
        }

        if ($response instanceof Elasticsearch) {
            return $response->asBool();
        }

        return (bool) $response;
    }
}
