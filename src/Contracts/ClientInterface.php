<?php

declare(strict_types=1);

namespace EsOrm\Contracts;

/**
 * Low level, thin adapter over an Elasticsearch client.
 *
 * The ORM never talks to HTTP directly: it always goes through this contract.
 * That keeps the whole package testable without a running Elasticsearch node
 * (see `EsOrm\Tests\Support\FakeClient`) and allows swapping the transport.
 *
 * Every method receives the raw Elasticsearch API parameters and returns the
 * raw Elasticsearch response as an array.
 */
interface ClientInterface
{
    /**
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    public function search(array $params): array;

    /**
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    public function count(array $params): array;

    /**
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    public function index(array $params): array;

    /**
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    public function get(array $params): array;

    /**
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    public function update(array $params): array;

    /**
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    public function delete(array $params): array;

    /**
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    public function bulk(array $params): array;

    /**
     * Multi get.
     *
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    public function mget(array $params): array;

    /**
     * Multi search (_msearch).
     *
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    public function msearch(array $params): array;

    /**
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    public function scroll(array $params): array;

    /**
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    public function clearScroll(array $params): array;

    /**
     * Open a Point In Time.
     *
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    public function openPointInTime(array $params): array;

    /**
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    public function closePointInTime(array $params): array;

    /**
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    public function reindex(array $params): array;

    /**
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    public function createIndex(array $params): array;

    /**
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    public function deleteIndex(array $params): array;

    /**
     * @param array<string, mixed> $params
     */
    public function indexExists(array $params): bool;

    /**
     * Raw index metadata (settings, mappings, aliases).
     *
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    public function getIndex(array $params): array;

    /**
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    public function getMapping(array $params): array;

    /**
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    public function putMapping(array $params): array;

    /**
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    public function refresh(array $params): array;

    /**
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    public function flush(array $params): array;

    /**
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    public function forceMerge(array $params): array;

    /**
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    public function closeIndex(array $params): array;

    /**
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    public function openIndex(array $params): array;

    /**
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    public function updateAliases(array $params): array;

    /**
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    public function getAlias(array $params): array;
}
