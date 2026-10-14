<?php

declare(strict_types=1);

namespace EsOrm\Client;

use Elastic\Elasticsearch\Client;
use Elastic\Elasticsearch\ClientBuilder;
use EsOrm\Exceptions\ConnectionException;
use Psr\Http\Client\ClientInterface as Psr18ClientInterface;
use Psr\Log\LoggerInterface;

/**
 * Builds the official Elasticsearch PHP client from a connection config array.
 *
 * This is the only place in the package that knows about the official client
 * builder, which keeps the rest of the ORM transport agnostic and easy to test.
 *
 * Supported configuration keys:
 *  - `hosts`            list<string> of `http(s)://host:port`
 *  - `cloud_id`         Elastic Cloud ID
 *  - `api_key`          API key (`id:key` or `key`, optionally with `api_key_id`)
 *  - `username`/`password`  HTTP basic authentication
 *  - `bearer_token`     `Authorization: Bearer <token>` header
 *  - `http_client`      a PSR-18 client instance to be used instead of discovery
 *  - `http_client_options` options forwarded to the underlying client (guzzle style)
 *  - `timeout` / `connect_timeout`  convenience shortcuts merged into the client options
 *  - `ssl_certificate`, `ssl_key`, `ssl_ca`, `ssl_verification`
 *  - `logger`           a PSR-3 logger
 */
final class ClientFactory
{
    /**
     * @param array<string, mixed> $config
     */
    public static function create(array $config, ?LoggerInterface $logger = null): Client
    {
        $builder = ClientBuilder::create();

        $builder->setHosts(self::hosts($config));

        if (isset($config['cloud_id']) && is_string($config['cloud_id']) && $config['cloud_id'] !== '') {
            $builder->setElasticCloudId($config['cloud_id']);
        }

        self::applyAuthentication($builder, $config);
        self::applyTls($builder, $config);

        $options = self::clientOptions(self::withBearerToken($config));
        if ($options !== []) {
            $builder->setHttpClientOptions($options);
        }

        if (isset($config['http_client']) && $config['http_client'] instanceof Psr18ClientInterface) {
            $builder->setHttpClient($config['http_client']);
        }

        $logger ??= self::configuredLogger($config);
        if ($logger instanceof LoggerInterface) {
            $builder->setLogger($logger);
        }

        return $builder->build();
    }

    /**
     * Normalise the configured hosts into the plain list of host URI strings
     * expected by the official client (`http(s)://host:port`).
     *
     * Both `['http://localhost:9200']` and `[['host' => 'http://localhost:9200']]`
     * forms are accepted.
     *
     * @param array<string, mixed> $config
     * @return list<string>
     */
    private static function hosts(array $config): array
    {
        $hosts = $config['hosts'] ?? [];

        if (is_string($hosts) && $hosts !== '') {
            $hosts = [$hosts];
        }

        if (!is_array($hosts) || $hosts === []) {
            $hosts = ['http://localhost:9200'];
        }

        $normalized = [];

        foreach ($hosts as $host) {
            if (is_string($host) && $host !== '') {
                $normalized[] = $host;

                continue;
            }

            if (is_array($host)) {
                $value = $host['host'] ?? null;

                if (is_string($value) && $value !== '') {
                    $normalized[] = $value;
                }
            }
        }

        if ($normalized === []) {
            $normalized = ['http://localhost:9200'];
        }

        return $normalized;
    }

    /**
     * @param array<string, mixed> $config
     */
    private static function applyAuthentication(ClientBuilder $builder, array $config): void
    {
        if (isset($config['api_key']) && is_string($config['api_key']) && $config['api_key'] !== '') {
            $id = $config['api_key_id'] ?? null;
            $builder->setApiKey($config['api_key'], is_string($id) ? $id : null);

            return;
        }

        if (isset($config['username'], $config['password'])
            && is_string($config['username'])
            && is_string($config['password'])
        ) {
            $builder->setBasicAuthentication($config['username'], $config['password']);
        }
    }

    /**
     * Turn a `bearer_token` into the `Authorization` header of the underlying
     * HTTP client options (the official builder has no dedicated method).
     *
     * @param array<string, mixed> $config
     * @return array<string, mixed>
     */
    private static function withBearerToken(array $config): array
    {
        $token = $config['bearer_token'] ?? null;

        if (!is_string($token) || $token === '') {
            return $config;
        }

        $options = (array) ($config['http_client_options'] ?? []);
        $headers = (array) ($options['headers'] ?? []);
        $headers['Authorization'] = 'Bearer ' . $token;
        $options['headers'] = $headers;
        $config['http_client_options'] = $options;

        return $config;
    }

    /**
     * @param array<string, mixed> $config
     */
    private static function applyTls(ClientBuilder $builder, array $config): void
    {
        if (isset($config['ssl_ca']) && is_string($config['ssl_ca']) && $config['ssl_ca'] !== '') {
            $builder->setCABundle($config['ssl_ca']);
        }

        if (isset($config['ssl_certificate']) && is_string($config['ssl_certificate']) && $config['ssl_certificate'] !== '') {
            $password = $config['ssl_certificate_password'] ?? null;
            $builder->setSSLCert($config['ssl_certificate'], is_string($password) ? $password : null);
        }

        if (isset($config['ssl_key']) && is_string($config['ssl_key']) && $config['ssl_key'] !== '') {
            $password = $config['ssl_key_password'] ?? null;
            $builder->setSSLKey($config['ssl_key'], is_string($password) ? $password : null);
        }

        if (array_key_exists('ssl_verification', $config)) {
            $builder->setSSLVerification((bool) $config['ssl_verification']);
        }
    }

    /**
     * @param array<string, mixed> $config
     * @return array<string, mixed>
     */
    private static function clientOptions(array $config): array
    {
        $options = [];

        if (isset($config['http_client_options']) && is_array($config['http_client_options'])) {
            $options = $config['http_client_options'];
        }

        if (isset($config['timeout'])) {
            $options['timeout'] = (float) $config['timeout'];
        }

        if (isset($config['connect_timeout'])) {
            $options['connect_timeout'] = (float) $config['connect_timeout'];
        }

        return $options;
    }

    /**
     * @param array<string, mixed> $config
     */
    private static function configuredLogger(array $config): ?LoggerInterface
    {
        $logger = $config['logger'] ?? null;

        return $logger instanceof LoggerInterface ? $logger : null;
    }

    /**
     * Assert that the official client is installed, giving a helpful error
     * message instead of a fatal "class not found" error.
     */
    public static function assertInstalled(): void
    {
        if (!class_exists(Client::class)) {
            throw new ConnectionException(
                'The official Elasticsearch PHP client is not installed. Run `composer require elasticsearch/elasticsearch`.',
            );
        }
    }
}
