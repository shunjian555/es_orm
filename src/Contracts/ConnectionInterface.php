<?php

declare(strict_types=1);

namespace EsOrm\Contracts;

/**
 * A named Elasticsearch connection: configuration + client + transport concerns
 * (retry, logging, ...).
 */
interface ConnectionInterface
{
    /**
     * The connection name, e.g. `default` or `logs`.
     */
    public function getName(): string;

    /**
     * The underlying (possibly fake) Elasticsearch client.
     */
    public function getClient(): ClientInterface;

    /**
     * Replace the underlying client. Mostly useful for testing and for
     * container driven wiring.
     */
    public function setClient(ClientInterface $client): void;

    /**
     * The whole connection configuration array.
     *
     * @return array<string, mixed>
     */
    public function getConfig(): array;

    /**
     * Dot-notation aware accessor for a single configuration value.
     */
    public function getConfigValue(string $key, mixed $default = null): mixed;

    /**
     * Execute a search request, applying retry policy and query logging.
     *
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    public function search(array $params): array;

    /**
     * Execute an arbitrary Elasticsearch request through the retry/logging
     * pipeline.
     *
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    public function request(string $operation, array $params): array;
}
