<?php

declare(strict_types=1);

namespace EsOrm\Tests\Support;

use EsOrm\Contracts\ClientInterface;
use RuntimeException;
use Throwable;

/**
 * An in-memory {@see ClientInterface} implementation used by the unit tests.
 *
 * It records every call (operation + params) so tests can assert the exact
 * request the ORM would send to Elasticsearch, and returns canned responses
 * that can be queued per operation.
 */
final class FakeClient implements ClientInterface
{
    /**
     * Recorded calls, in order: [['operation' => string, 'params' => array], ...].
     *
     * @var list<array{operation: string, params: array<string, mixed>}>
     */
    public array $calls = [];

    /**
     * Queued responses per operation (consumed FIFO).
     *
     * @var array<string, list<array<string, mixed>|bool|callable>>
     */
    private array $responses = [];

    /**
     * @param array<string, array<string, mixed>|bool|callable> $responses
     */
    public function __construct(array $responses = [])
    {
        foreach ($responses as $operation => $response) {
            $this->queue($operation, $response);
        }
    }

    /**
     * Register a single (replacing) response for an operation.
     *
     * @param array<string, mixed>|bool|callable $response
     */
    public function setResponse(string $operation, array|bool|callable $response): self
    {
        $this->responses[$operation] = [$response];

        return $this;
    }

    /**
     * Append a response to the queue of an operation.
     *
     * @param array<string, mixed>|bool|callable $response
     */
    public function queue(string $operation, array|bool|callable $response): self
    {
        $this->responses[$operation][] = $response;

        return $this;
    }

    /**
     * Resolve (and consume) the response for an operation.
     *
     * @param array<string, mixed> $params
     * @return array<string, mixed>|bool
     */
    private function response(string $operation, array $params): array|bool
    {
        if (isset($this->responses[$operation]) && $this->responses[$operation] !== []) {
            $response = array_shift($this->responses[$operation]);
        } else {
            $response = $this->defaultResponse($operation);
        }

        if (is_callable($response)) {
            $response = $response($params);
        }

        return is_array($response) || is_bool($response) ? $response : [];
    }

    public function reset(): void
    {
        $this->calls = [];
        $this->responses = [];
    }

    /**
     * @return list<array{operation: string, params: array<string, mixed>}>
     */
    public function callsFor(string $operation): array
    {
        return array_values(array_filter(
            $this->calls,
            static fn (array $call): bool => $call['operation'] === $operation,
        ));
    }

    public function callCount(string $operation): int
    {
        return count($this->callsFor($operation));
    }

    /**
     * @return array<string, mixed>
     */
    public function lastParams(string $operation): array
    {
        $calls = $this->callsFor($operation);

        return $calls === [] ? [] : $calls[array_key_last($calls)]['params'];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function lastCall(string $operation): ?array
    {
        $calls = $this->callsFor($operation);

        return $calls === [] ? null : $calls[array_key_last($calls)]['params'];
    }

    public function lastOperation(): ?string
    {
        return $this->calls === [] ? null : $this->calls[array_key_last($this->calls)]['operation'];
    }

    /**
     * @param array<string, mixed> $params
     */
    private function record(string $operation, array $params): void
    {
        $this->calls[] = ['operation' => $operation, 'params' => $params];
    }

    /**
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    private function arrayResponse(string $operation, array $params): array
    {
        $this->record($operation, $params);

        $response = $this->response($operation, $params);

        return is_array($response) ? $response : [];
    }

    /**
     * @return array<string, mixed>
     */
    private function defaultResponse(string $operation): array
    {
        return match ($operation) {
            'search' => ['took' => 1, 'timed_out' => false, 'hits' => ['total' => ['value' => 0], 'hits' => []]],
            'count' => ['count' => 0],
            'index' => ['_index' => 'default', '_id' => '1', 'result' => 'created'],
            'get' => ['found' => false],
            'update' => ['result' => 'updated'],
            'delete' => ['result' => 'deleted'],
            'deleteByQuery' => ['took' => 10, 'total' => 0, 'deleted' => 0],
            'bulk' => ['errors' => false, 'items' => []],
            'mget' => ['docs' => []],
            'msearch' => ['responses' => []],
            'scroll' => ['_scroll_id' => null, 'hits' => ['total' => ['value' => 0], 'hits' => []]],
            'clearScroll' => ['succeeded' => true],
            'openPointInTime' => ['id' => 'pit-1'],
            'closePointInTime' => ['succeeded' => true],
            'reindex' => ['created' => 0, 'updated' => 0],
            'createIndex' => ['acknowledged' => true],
            'deleteIndex' => ['acknowledged' => true],
            'getIndex' => [],
            'getMapping' => [],
            'putMapping' => ['acknowledged' => true],
            'refresh' => ['_shards' => ['successful' => 1]],
            'flush' => ['_shards' => ['successful' => 1]],
            'forceMerge' => ['_shards' => ['successful' => 1]],
            'closeIndex' => ['acknowledged' => true],
            'openIndex' => ['acknowledged' => true],
            'updateAliases' => ['acknowledged' => true],
            'getAlias' => [],
            default => [],
        };
    }

    /* ------------------------------------------------------------------
     |  ClientInterface
     | ------------------------------------------------------------------ */

    public function search(array $params): array
    {
        return $this->arrayResponse('search', $params);
    }

    public function count(array $params): array
    {
        return $this->arrayResponse('count', $params);
    }

    public function index(array $params): array
    {
        return $this->arrayResponse('index', $params);
    }

    public function get(array $params): array
    {
        return $this->arrayResponse('get', $params);
    }

    public function update(array $params): array
    {
        return $this->arrayResponse('update', $params);
    }

    public function delete(array $params): array
    {
        return $this->arrayResponse('delete', $params);
    }

    public function deleteByQuery(array $params): array
    {
        return $this->arrayResponse('deleteByQuery', $params);
    }

    public function bulk(array $params): array
    {
        return $this->arrayResponse('bulk', $params);
    }

    public function mget(array $params): array
    {
        return $this->arrayResponse('mget', $params);
    }

    public function msearch(array $params): array
    {
        return $this->arrayResponse('msearch', $params);
    }

    public function scroll(array $params): array
    {
        return $this->arrayResponse('scroll', $params);
    }

    public function clearScroll(array $params): array
    {
        return $this->arrayResponse('clearScroll', $params);
    }

    public function openPointInTime(array $params): array
    {
        return $this->arrayResponse('openPointInTime', $params);
    }

    public function closePointInTime(array $params): array
    {
        return $this->arrayResponse('closePointInTime', $params);
    }

    public function reindex(array $params): array
    {
        return $this->arrayResponse('reindex', $params);
    }

    public function createIndex(array $params): array
    {
        return $this->arrayResponse('createIndex', $params);
    }

    public function deleteIndex(array $params): array
    {
        return $this->arrayResponse('deleteIndex', $params);
    }

    public function indexExists(array $params): bool
    {
        $this->record('indexExists', $params);

        $response = $this->response('indexExists', $params);

        return is_bool($response) ? $response : (bool) $response;
    }

    public function getIndex(array $params): array
    {
        return $this->arrayResponse('getIndex', $params);
    }

    public function getMapping(array $params): array
    {
        return $this->arrayResponse('getMapping', $params);
    }

    public function putMapping(array $params): array
    {
        return $this->arrayResponse('putMapping', $params);
    }

    public function refresh(array $params): array
    {
        return $this->arrayResponse('refresh', $params);
    }

    public function flush(array $params): array
    {
        return $this->arrayResponse('flush', $params);
    }

    public function forceMerge(array $params): array
    {
        return $this->arrayResponse('forceMerge', $params);
    }

    public function closeIndex(array $params): array
    {
        return $this->arrayResponse('closeIndex', $params);
    }

    public function openIndex(array $params): array
    {
        return $this->arrayResponse('openIndex', $params);
    }

    public function updateAliases(array $params): array
    {
        return $this->arrayResponse('updateAliases', $params);
    }

    public function getAlias(array $params): array
    {
        return $this->arrayResponse('getAlias', $params);
    }

    /* ------------------------------------------------------------------
     |  Convenience for exception scenarios
     | ------------------------------------------------------------------ */

    /**
     * Make the next call to `$operation` throw the given exception.
     */
    public function willThrow(string $operation, Throwable $error): self
    {
        return $this->setResponse($operation, static function () use ($error): never {
            throw $error;
        });
    }

    public static function fail(string $message = 'transport failure'): RuntimeException
    {
        return new RuntimeException($message);
    }
}
