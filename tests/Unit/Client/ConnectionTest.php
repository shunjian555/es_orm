<?php

declare(strict_types=1);

namespace EsOrm\Tests\Unit\Client;

use Elastic\Elasticsearch\Exception\ClientResponseException;
use Elastic\Elasticsearch\Exception\ServerResponseException;
use EsOrm\Client\Connection;
use EsOrm\Client\RetryHandler;
use EsOrm\Exceptions\BulkException;
use EsOrm\Exceptions\ConnectionException;
use EsOrm\Exceptions\IndexException;
use EsOrm\Exceptions\QueryException;
use EsOrm\Support\QueryLog;
use EsOrm\Tests\TestCase;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\DataProvider;

final class ConnectionTest extends TestCase
{
    /**
     * A connection with retries disabled, so failures surface immediately.
     *
     * @param array<string, mixed> $config
     */
    private function makeConnection(?QueryLog $queryLog = null, array $config = []): Connection
    {
        return new Connection(
            'default',
            $this->client,
            $config === [] ? ['timeout' => 5, 'retry' => ['max_retries' => 1]] : $config,
            new RetryHandler(0, 0, 2.0, 2000),
            $queryLog,
        );
    }

    public function test_it_exposes_name_client_and_config(): void
    {
        $connection = $this->makeConnection();

        self::assertSame('default', $connection->getName());
        self::assertSame($this->client, $connection->getClient());
        self::assertSame(['timeout' => 5, 'retry' => ['max_retries' => 1]], $connection->getConfig());
    }

    public function test_it_reads_config_values_using_dot_notation(): void
    {
        $connection = $this->makeConnection();

        self::assertSame(5, $connection->getConfigValue('timeout'));
        self::assertSame(1, $connection->getConfigValue('retry.max_retries'));
        self::assertSame('fallback', $connection->getConfigValue('missing.key', 'fallback'));
    }

    public function test_it_can_replace_its_client(): void
    {
        $connection = $this->makeConnection();
        $replacement = new \EsOrm\Tests\Support\FakeClient();

        $connection->setClient($replacement);

        self::assertSame($replacement, $connection->getClient());
    }

    public function test_it_returns_its_retry_handler(): void
    {
        $handler = new RetryHandler(4, 0);

        $connection = new Connection('default', $this->client, [], $handler);

        self::assertSame($handler, $connection->getRetryHandler());
    }

    public function test_it_falls_back_to_a_default_retry_handler(): void
    {
        $connection = new Connection('default', $this->client);

        self::assertSame(3, $connection->getRetryHandler()->maxRetries());
    }

    public function test_search_delegates_to_the_client(): void
    {
        $this->client->setResponse('search', ['hits' => ['total' => ['value' => 3]]]);

        $result = $this->makeConnection()->search(['index' => 'users', 'body' => []]);

        self::assertSame(['hits' => ['total' => ['value' => 3]]], $result);
        self::assertSame(1, $this->client->callCount('search'));
        self::assertSame('users', $this->client->lastParams('search')['index']);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function operationProvider(): array
    {
        $operations = [
            'search', 'count', 'index', 'get', 'update', 'delete', 'bulk', 'mget', 'msearch',
            'scroll', 'clearScroll', 'openPointInTime', 'closePointInTime', 'reindex',
            'createIndex', 'deleteIndex', 'getIndex', 'getMapping', 'putMapping', 'refresh',
            'flush', 'forceMerge', 'closeIndex', 'openIndex', 'updateAliases', 'getAlias',
        ];

        return array_combine($operations, array_map(static fn (string $op): array => [$op], $operations));
    }

    #[DataProvider('operationProvider')]
    public function test_request_routes_to_the_matching_client_method(string $operation): void
    {
        $this->makeConnection()->request($operation, ['index' => 'users']);

        self::assertSame(1, $this->client->callCount($operation));
    }

    /**
     * The current implementation wraps *every* throwable raised by `execute()`
     * (including its own "unknown operation" ConnectionException) through the
     * error mapper, so an unknown operation surfaces as a QueryException whose
     * message embeds the original reason. Reported as a suspected bug.
     */
    public function test_an_unknown_operation_is_reported_as_a_query_exception(): void
    {
        $this->expectException(QueryException::class);
        $this->expectExceptionMessage('Elasticsearch query failed on POST /nope: Unknown Elasticsearch operation [nope]');

        $this->makeConnection()->request('nope', []);
    }

    public function test_it_records_a_successful_query_log_entry(): void
    {
        $log = new QueryLog();
        $this->client->setResponse('search', ['hits' => ['total' => ['value' => 1]]]);

        $this->makeConnection($log)->request('search', [
            'index' => 'users',
            'body' => ['query' => ['match_all' => []]],
        ]);

        $entry = $log->last();
        self::assertNotNull($entry);
        self::assertSame('search', $entry->operation);
        self::assertSame('POST', $entry->method);
        self::assertSame('/users/_search', $entry->endpoint);
        self::assertSame('users', $entry->index);
        self::assertSame(['query' => ['match_all' => []]], $entry->body);
        self::assertTrue($entry->successful);
        self::assertNull($entry->error);
    }

    public function test_it_omits_the_index_from_the_endpoint_when_absent(): void
    {
        $log = new QueryLog();

        $this->makeConnection($log)->request('search', []);

        self::assertSame('/_search', $log->last()?->endpoint);
        self::assertNull($log->last()?->index);
    }

    public function test_it_expands_an_array_index_into_a_comma_list(): void
    {
        $log = new QueryLog();

        $this->makeConnection($log)->request('search', ['index' => ['users', 'logs']]);

        self::assertSame('users,logs', $log->last()?->index);
        self::assertSame('/users,logs/_search', $log->last()?->endpoint);
    }

    public function test_it_places_the_document_id_in_the_endpoint(): void
    {
        $log = new QueryLog();

        $this->makeConnection($log)->request('index', ['index' => 'users', 'id' => 7, 'body' => ['name' => 'Tom']]);

        self::assertSame('PUT', $log->last()?->method);
        self::assertSame('/users/_doc/7', $log->last()?->endpoint);
    }

    public function test_it_records_a_failed_query_log_entry(): void
    {
        $log = new QueryLog();
        $this->client->willThrow('search', $this->clientError(400));

        try {
            $this->makeConnection($log)->request('search', ['index' => 'users']);
            self::fail('An exception should have been thrown.');
        } catch (QueryException) {
            // expected
        }

        $entry = $log->last();
        self::assertNotNull($entry);
        self::assertFalse($entry->successful);
        self::assertSame('request failed', $entry->error);
    }

    public function test_a_retryable_transport_error_becomes_a_connection_exception(): void
    {
        $this->client->willThrow('search', new ConnectException('Connection refused', new Request('GET', '/')));

        $this->expectException(ConnectionException::class);
        $this->expectExceptionMessage('Elasticsearch connection error on POST /_search: Connection refused');

        $this->makeConnection()->request('search', []);
    }

    public function test_a_5xx_response_becomes_a_connection_exception(): void
    {
        $this->client->willThrow('search', $this->serverError(503, 'cluster is down'));

        $this->expectException(ConnectionException::class);

        $this->makeConnection()->request('search', []);
    }

    public function test_a_429_response_becomes_a_connection_exception(): void
    {
        $this->client->willThrow('search', $this->clientError(429, 'too many requests'));

        $this->expectException(ConnectionException::class);

        $this->makeConnection()->request('search', []);
    }

    public function test_an_index_operation_failure_becomes_an_index_exception(): void
    {
        $this->client->willThrow('refresh', $this->clientError(400, 'invalid index'));

        $this->expectException(IndexException::class);
        $this->expectExceptionMessage('Elasticsearch index operation failed on POST /_refresh: invalid index');

        $this->makeConnection()->request('refresh', []);
    }

    public function test_a_bulk_failure_becomes_a_bulk_exception(): void
    {
        $this->client->willThrow('bulk', $this->clientError(400, 'malformed bulk'));

        $this->expectException(BulkException::class);
        $this->expectExceptionMessage('Elasticsearch bulk request failed on POST /_bulk: malformed bulk');

        $this->makeConnection()->request('bulk', []);
    }

    public function test_a_normal_failure_becomes_a_query_exception(): void
    {
        $this->client->willThrow('search', $this->clientError(400, 'parsing error'));

        $this->expectException(QueryException::class);
        $this->expectExceptionMessage('Elasticsearch query failed on POST /_search: parsing error');

        $this->makeConnection()->request('search', []);
    }

    public function test_it_falls_back_to_the_error_message_when_no_reason_is_available(): void
    {
        $this->client->willThrow('search', new QueryException('raw failure'));

        $this->expectException(QueryException::class);
        $this->expectExceptionMessage('Elasticsearch query failed on POST /_search: raw failure');

        $this->makeConnection()->request('search', []);
    }

    /**
     * Build a 4xx elasticsearch exception carrying a JSON error body.
     */
    private function clientError(int $status, string $reason = 'boom'): ClientResponseException
    {
        $exception = new ClientResponseException('request failed', $status);

        return $exception->setResponse(new Response(
            $status,
            ['Content-Type' => 'application/json'],
            json_encode(['error' => ['reason' => $reason]], JSON_THROW_ON_ERROR),
        ));
    }

    private function serverError(int $status, string $reason): ServerResponseException
    {
        $exception = new ServerResponseException('server failed', $status);

        return $exception->setResponse(new Response(
            $status,
            ['Content-Type' => 'application/json'],
            json_encode(['error' => ['reason' => $reason]], JSON_THROW_ON_ERROR),
        ));
    }
}
