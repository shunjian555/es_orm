<?php

declare(strict_types=1);

namespace EsOrm\Client;

use EsOrm\Contracts\ClientInterface;
use EsOrm\Contracts\ConnectionInterface;
use EsOrm\Exceptions\BulkException;
use EsOrm\Exceptions\ConnectionException;
use EsOrm\Exceptions\IndexException;
use EsOrm\Exceptions\QueryException;
use EsOrm\Support\Arr;
use EsOrm\Support\QueryLog;
use EsOrm\Support\QueryLogEntry;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * A named Elasticsearch connection.
 *
 * The connection owns the transport concerns that must not leak into the Model
 * or Query Builder layers: retry policy, query logging and exception mapping.
 */
final class Connection implements ConnectionInterface
{
    /**
     * Operation name => [HTTP method, endpoint template].
     *
     * Used for the development-time query log; the actual request is performed
     * by the official client, so this is purely descriptive.
     *
     * @var array<string, array{0: string, 1: string}>
     */
    private const OPERATIONS = [
        'search' => ['POST', '/{index}/_search'],
        'count' => ['POST', '/{index}/_count'],
        'index' => ['PUT', '/{index}/_doc/{id}'],
        'get' => ['GET', '/{index}/_doc/{id}'],
        'update' => ['POST', '/{index}/_update/{id}'],
        'delete' => ['DELETE', '/{index}/_doc/{id}'],
        'deleteByQuery' => ['POST', '/{index}/_delete_by_query'],
        'bulk' => ['POST', '/_bulk'],
        'mget' => ['POST', '/_mget'],
        'msearch' => ['POST', '/_msearch'],
        'scroll' => ['POST', '/_search/scroll'],
        'clearScroll' => ['DELETE', '/_search/scroll'],
        'openPointInTime' => ['POST', '/{index}/_pit'],
        'closePointInTime' => ['DELETE', '/_pit'],
        'reindex' => ['POST', '/_reindex'],
        'createIndex' => ['PUT', '/{index}'],
        'deleteIndex' => ['DELETE', '/{index}'],
        'getIndex' => ['GET', '/{index}'],
        'getMapping' => ['GET', '/{index}/_mapping'],
        'putMapping' => ['PUT', '/{index}/_mapping'],
        'refresh' => ['POST', '/{index}/_refresh'],
        'flush' => ['POST', '/{index}/_flush'],
        'forceMerge' => ['POST', '/{index}/_forcemerge'],
        'closeIndex' => ['POST', '/{index}/_close'],
        'openIndex' => ['POST', '/{index}/_open'],
        'updateAliases' => ['POST', '/_aliases'],
        'getAlias' => ['GET', '/{index}/_alias'],
    ];

    /**
     * Operations that are administrative index operations.
     *
     * @var list<string>
     */
    private const INDEX_OPERATIONS = [
        'createIndex',
        'deleteIndex',
        'getIndex',
        'getMapping',
        'putMapping',
        'refresh',
        'flush',
        'forceMerge',
        'closeIndex',
        'openIndex',
        'updateAliases',
        'getAlias',
    ];

    /**
     * @param array<string, mixed> $config
     */
    public function __construct(
        private readonly string $name,
        private ClientInterface $client,
        private readonly array $config = [],
        private readonly ?RetryHandler $retry = null,
        private readonly ?QueryLog $queryLog = null,
        private readonly ?LoggerInterface $logger = null,
    ) {
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getClient(): ClientInterface
    {
        return $this->client;
    }

    public function setClient(ClientInterface $client): void
    {
        $this->client = $client;
    }

    /**
     * @return array<string, mixed>
     */
    public function getConfig(): array
    {
        return $this->config;
    }

    public function getConfigValue(string $key, mixed $default = null): mixed
    {
        return Arr::get($this->config, $key, $default);
    }

    public function getRetryHandler(): RetryHandler
    {
        return $this->retry ?? new RetryHandler();
    }

    /**
     * The query log shared by every connection of this manager.
     */
    public function getQueryLog(): ?QueryLog
    {
        return $this->queryLog;
    }

    /**
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    public function search(array $params): array
    {
        return $this->request('search', $params);
    }

    /**
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    public function request(string $operation, array $params): array
    {
        $meta = $this->describe($operation, $params);
        $startedAt = hrtime(true);

        try {
            $response = $this->getRetryHandler()->run(fn (): array => $this->execute($operation, $params));
        } catch (Throwable $error) {
            $this->writeLog($operation, $meta, $startedAt, false, $error);
            $this->throwError($operation, $meta, $error);
        }

        $this->writeLog($operation, $meta, $startedAt, true, null);

        return $response;
    }

    /**
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    private function execute(string $operation, array $params): array
    {
        return match ($operation) {
            'search' => $this->client->search($params),
            'count' => $this->client->count($params),
            'index' => $this->client->index($params),
            'get' => $this->client->get($params),
            'update' => $this->client->update($params),
            'delete' => $this->client->delete($params),
            'deleteByQuery' => $this->client->deleteByQuery($params),
            'bulk' => $this->client->bulk($params),
            'mget' => $this->client->mget($params),
            'msearch' => $this->client->msearch($params),
            'scroll' => $this->client->scroll($params),
            'clearScroll' => $this->client->clearScroll($params),
            'openPointInTime' => $this->client->openPointInTime($params),
            'closePointInTime' => $this->client->closePointInTime($params),
            'reindex' => $this->client->reindex($params),
            'createIndex' => $this->client->createIndex($params),
            'deleteIndex' => $this->client->deleteIndex($params),
            'getIndex' => $this->client->getIndex($params),
            'getMapping' => $this->client->getMapping($params),
            'putMapping' => $this->client->putMapping($params),
            'refresh' => $this->client->refresh($params),
            'flush' => $this->client->flush($params),
            'forceMerge' => $this->client->forceMerge($params),
            'closeIndex' => $this->client->closeIndex($params),
            'openIndex' => $this->client->openIndex($params),
            'updateAliases' => $this->client->updateAliases($params),
            'getAlias' => $this->client->getAlias($params),
            default => throw new ConnectionException(sprintf('Unknown Elasticsearch operation [%s].', $operation)),
        };
    }

    /**
     * @param array<string, mixed> $params
     * @return array{method: string, endpoint: string, index: string|null, body: array<string, mixed>}
     */
    private function describe(string $operation, array $params): array
    {
        [$method, $template] = self::OPERATIONS[$operation] ?? ['POST', '/' . $operation];

        $index = $params['index'] ?? null;
        if (is_array($index)) {
            $index = implode(',', array_map(static fn (mixed $value): string => (string) $value, $index));
        }
        $index = is_string($index) && $index !== '' ? $index : null;

        $endpoint = $index === null
            ? str_replace(['{index}/', '{index}'], '', $template)
            : str_replace('{index}', $index, $template);

        if (isset($params['id'])) {
            $endpoint = str_replace('{id}', (string) $params['id'], $endpoint);
        }
        $endpoint = str_replace('{id}', '', $endpoint);

        $body = $params['body'] ?? [];

        return [
            'method' => $method,
            'endpoint' => $endpoint === '' ? '/' : $endpoint,
            'index' => $index,
            'body' => is_array($body) ? $body : [],
        ];
    }

    /**
     * @param array{method: string, endpoint: string, index: string|null, body: array<string, mixed>} $meta
     */
    private function writeLog(string $operation, array $meta, int $startedAt, bool $successful, ?Throwable $error): void
    {
        $duration = (hrtime(true) - $startedAt) / 1_000_000;

        if ($this->queryLog instanceof QueryLog) {
            $this->queryLog->record(new QueryLogEntry(
                operation: $operation,
                method: $meta['method'],
                endpoint: $meta['endpoint'],
                index: $meta['index'],
                body: $meta['body'],
                duration: $duration,
                successful: $successful,
                error: $error?->getMessage(),
            ));
        }

        if ($this->logger instanceof LoggerInterface) {
            $context = [
                'connection' => $this->name,
                'operation' => $operation,
                'method' => $meta['method'],
                'endpoint' => $meta['endpoint'],
                'index' => $meta['index'],
                'duration_ms' => round($duration, 2),
            ];

            if ($successful) {
                $this->logger->debug('Elasticsearch request executed.', $context);
            } else {
                $context['exception'] = $error;

                $this->logger->error('Elasticsearch request failed.', $context);
            }
        }
    }

    /**
     * @param array{method: string, endpoint: string, index: string|null, body: array<string, mixed>} $meta
     * @throws ConnectionException|IndexException|BulkException|QueryException
     */
    private function throwError(string $operation, array $meta, Throwable $error): never
    {
        $identifier = $meta['method'] . ' ' . $meta['endpoint'];
        $status = RetryHandler::statusCode($error);
        $code = (int) $error->getCode();

        if ($this->getRetryHandler()->isRetryable($error)) {
            throw new ConnectionException(
                sprintf('Elasticsearch connection error on %s: %s', $identifier, $error->getMessage()),
                $code,
                $error,
            );
        }

        $reason = $this->reasonFrom($error) ?? $error->getMessage();

        if ($status !== null && $status >= 500) {
            throw new ConnectionException(
                sprintf('Elasticsearch server error (%d) on %s: %s', $status, $identifier, $reason),
                $code,
                $error,
            );
        }

        if ($operation === 'bulk') {
            throw new BulkException(
                sprintf('Elasticsearch bulk request failed on %s: %s', $identifier, $reason),
                [],
                $error,
            );
        }

        if (in_array($operation, self::INDEX_OPERATIONS, true)) {
            throw new IndexException(
                sprintf('Elasticsearch index operation failed on %s: %s', $identifier, $reason),
                $code,
                $error,
            );
        }

        throw new QueryException(
            sprintf('Elasticsearch query failed on %s: %s', $identifier, $reason),
            $code,
            $error,
        );
    }

    /**
     * Extract the human readable reason of an Elasticsearch error response.
     */
    private function reasonFrom(Throwable $error): ?string
    {
        if (!method_exists($error, 'getResponse')) {
            return null;
        }

        $response = $error->getResponse();

        if (!is_object($response) || !method_exists($response, 'getBody')) {
            return null;
        }

        try {
            $body = (string) $response->getBody();
            $decoded = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
        } catch (Throwable) {
            return null;
        }

        if (!is_array($decoded)) {
            return null;
        }

        $reason = Arr::get($decoded, 'error.reason')
            ?? Arr::get($decoded, 'error.root_cause.0.reason');

        return is_string($reason) ? $reason : null;
    }
}
