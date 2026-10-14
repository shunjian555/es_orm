<?php

declare(strict_types=1);

namespace EsOrm\Contracts;

/**
 * Manage the lifecycle of indices and their mappings.
 *
 * Every operation is expressed with the raw Elasticsearch API semantics and
 * returns plain arrays (or booleans for existence checks) so the manager can be
 * used both from plain PHP and from the Laravel integration.
 */
interface IndexManagerInterface
{
    /**
     * Create an index, optionally applying a mapping and settings.
     *
     * @param array<string, mixed> $settings Extra index settings (merged into the mapping body).
     * @param array<string, mixed> $options  Additional raw create-index parameters (e.g. `wait_for_active_shards`).
     */
    public function createIndex(string $index, ?MappingInterface $mapping = null, array $settings = [], array $options = []): bool;

    /**
     * Delete an index. Returns false when the index does not exist.
     */
    public function deleteIndex(string $index): bool;

    /**
     * Whether the index exists.
     */
    public function indexExists(string $index): bool;

    /**
     * Raw index metadata (settings, mappings, aliases) as returned by Elasticsearch.
     *
     * @return array<string, mixed>
     */
    public function getIndex(string $index): array;

    /**
     * The mappings of an index, keyed by concrete index name.
     *
     * @return array<string, mixed>
     */
    public function getMapping(string $index): array;

    /**
     * Add or update the mapping of an existing index.
     *
     * @param MappingInterface|array<string, mixed> $mapping A mapping object or a raw mappings body.
     */
    public function putMapping(string $index, MappingInterface|array $mapping): bool;

    /**
     * Refresh an index so recent changes become visible to search.
     */
    public function refresh(string $index): bool;

    /**
     * Flush an index (persist the translog to disk).
     */
    public function flush(string $index): bool;

    /**
     * Force-merge the segments of an index.
     *
     * @param array<string, mixed> $options e.g. `['max_num_segments' => 1]`.
     */
    public function forceMerge(string $index, array $options = []): bool;

    /**
     * Close an index (free its resources while keeping the data on disk).
     */
    public function close(string $index): bool;

    /**
     * Re-open a previously closed index.
     */
    public function open(string $index): bool;
}
