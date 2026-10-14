<?php

declare(strict_types=1);

namespace EsOrm\Tests\Unit\Client;

use Elastic\Elasticsearch\Client;
use EsOrm\Client\ClientFactory;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ClientFactoryTest extends TestCase
{
    public function test_assert_installed_passes_when_the_official_client_is_present(): void
    {
        ClientFactory::assertInstalled();

        $client = ClientFactory::create(['hosts' => ['http://localhost:9200']]);

        self::assertInstanceOf(Client::class, $client);
    }

    public function test_it_builds_the_official_client(): void
    {
        $client = ClientFactory::create(['hosts' => ['http://localhost:9200']]);

        self::assertInstanceOf(Client::class, $client);
    }

    /**
     * @return array<string, array{array<string, mixed>, string}>
     */
    public static function hostProvider(): array
    {
        return [
            'string host' => [['hosts' => 'http://es.one:9200'], 'es.one'],
            'list of strings' => [['hosts' => ['http://es.two:9200']], 'es.two'],
            'list of arrays' => [['hosts' => [['host' => 'http://es.three:9200']]], 'es.three'],
            'default host when none given' => [[], 'localhost'],
        ];
    }

    /**
     * @param array<string, mixed> $config
     */
    #[DataProvider('hostProvider')]
    public function test_it_normalises_the_configured_hosts(array $config, string $expectedHost): void
    {
        $mock = new MockHandler([$this->okResponse()]);
        $httpClient = new GuzzleClient(['handler' => HandlerStack::create($mock)]);

        $client = ClientFactory::create($config + ['http_client' => $httpClient]);
        $client->search(['index' => 'users']);

        $request = $mock->getLastRequest();
        self::assertNotNull($request);
        self::assertSame($expectedHost, $request->getUri()->getHost());
    }

    public function test_it_uses_the_provided_http_client(): void
    {
        $mock = new MockHandler([$this->okResponse()]);
        $httpClient = new GuzzleClient(['handler' => HandlerStack::create($mock)]);

        $client = ClientFactory::create(['hosts' => ['http://localhost:9200'], 'http_client' => $httpClient]);
        $client->search(['index' => 'users']);

        $request = $mock->getLastRequest();
        self::assertNotNull($request);
        self::assertSame('/users/_search', $request->getUri()->getPath());
    }

    public function test_it_builds_a_client_with_a_bearer_token(): void
    {
        $client = ClientFactory::create([
            'hosts' => ['http://localhost:9200'],
            'bearer_token' => 'secret-token',
        ]);

        self::assertInstanceOf(Client::class, $client);
    }

    private function okResponse(): Response
    {
        return new Response(200, [
            'Content-Type' => 'application/json',
            'X-Elastic-Product' => 'Elasticsearch',
        ], '{"acknowledged":true}');
    }
}
