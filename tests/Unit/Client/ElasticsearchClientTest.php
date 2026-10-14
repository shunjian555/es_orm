<?php

declare(strict_types=1);

namespace EsOrm\Tests\Unit\Client;

use Elastic\Elasticsearch\Client;
use EsOrm\Client\ClientFactory;
use EsOrm\Client\ElasticsearchClient;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;

/**
 * Exercises the thin adapter over the official Elasticsearch client.
 *
 * The official client is built for real, but its PSR-18 HTTP client is replaced
 * by a Guzzle {@see MockHandler} so every request stays fully offline. Only
 * configurations without `http_client_options` are used, because the official
 * builder discards an injected PSR-18 client as soon as client options are set.
 */
final class ElasticsearchClientTest extends TestCase
{
    public function test_it_wraps_and_exposes_the_official_client(): void
    {
        [$adapter, , $official] = $this->adapter([$this->okResponse(['hits' => []])]);

        self::assertSame($official, $adapter->client());
    }

    public function test_from_config_returns_an_adapter_over_a_real_client(): void
    {
        $mock = new MockHandler([]);
        $httpClient = new GuzzleClient(['handler' => HandlerStack::create($mock)]);

        $adapter = ElasticsearchClient::fromConfig(['hosts' => ['http://localhost:9200'], 'http_client' => $httpClient]);

        self::assertInstanceOf(Client::class, $adapter->client());
    }

    public function test_search_decodes_the_response_and_forwards_the_request(): void
    {
        $expected = ['took' => 2, 'hits' => ['total' => ['value' => 1], 'hits' => []]];
        [$adapter, $mock] = $this->adapter([$this->okResponse($expected)]);

        $result = $adapter->search(['index' => 'users', 'body' => ['query' => ['match_all' => []]]]);

        self::assertSame($expected, $result);
        $request = $mock->getLastRequest();
        self::assertNotNull($request);
        self::assertSame('/users/_search', $request->getUri()->getPath());
        self::assertSame(
            ['query' => ['match_all' => []]],
            json_decode((string) $request->getBody(), true, 512, JSON_THROW_ON_ERROR),
        );
    }

    public function test_count_decodes_the_response(): void
    {
        [$adapter] = $this->adapter([$this->okResponse(['count' => 42])]);

        self::assertSame(['count' => 42], $adapter->count(['index' => 'users']));
    }

    public function test_index_exists_returns_true_on_a_2xx_response(): void
    {
        [$adapter] = $this->adapter([$this->okResponse(['acknowledged' => true])]);

        self::assertTrue($adapter->indexExists(['index' => 'users']));
    }

    public function test_index_exists_returns_false_on_a_404_response(): void
    {
        $notFound = new Response(404, ['Content-Type' => 'application/json'], '{"error":"not found"}');
        [$adapter] = $this->adapter([$notFound]);

        self::assertFalse($adapter->indexExists(['index' => 'users']));
    }

    /**
     * @param list<Response> $responses
     * @return array{0: ElasticsearchClient, 1: MockHandler, 2: Client}
     */
    private function adapter(array $responses): array
    {
        $mock = new MockHandler($responses);
        $httpClient = new GuzzleClient(['handler' => HandlerStack::create($mock)]);
        $official = ClientFactory::create(['hosts' => ['http://localhost:9200'], 'http_client' => $httpClient]);

        return [new ElasticsearchClient($official), $mock, $official];
    }

    /**
     * @param array<string, mixed> $body
     */
    private function okResponse(array $body): Response
    {
        return new Response(200, [
            'Content-Type' => 'application/json',
            'X-Elastic-Product' => 'Elasticsearch',
        ], json_encode($body, JSON_THROW_ON_ERROR));
    }
}
