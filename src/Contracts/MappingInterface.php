<?php

declare(strict_types=1);

namespace EsOrm\Contracts;

/**
 * A declarative Elasticsearch index mapping.
 *
 * A mapping is a pure, framework agnostic description of the properties of an
 * index (plus optional settings). It never talks to Elasticsearch by itself:
 * the {@see IndexManagerInterface} knows how to turn it into a create-index or
 * put-mapping request.
 */
interface MappingInterface
{
    /**
     * The index (or concrete index name) this mapping belongs to.
     */
    public function getIndex(): string;

    /**
     * The index settings (shards, replicas, analyzers, ...).
     *
     * @return array<string, mixed>
     */
    public function settings(): array;

    /**
     * The `mappings` body of the mapping: `['properties' => [...]]`.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array;

    /**
     * The full create-index body: `['settings' => [...], 'mappings' => [...]]`.
     *
     * The `settings` key is omitted when no settings are configured.
     *
     * @return array<string, mixed>
     */
    public function toIndexBody(): array;
}
