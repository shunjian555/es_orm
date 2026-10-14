<?php

declare(strict_types=1);

namespace EsOrm\Contracts;

use JsonSerializable;

/**
 * The narrow, public contract of an ORM model.
 */
interface ModelInterface extends JsonSerializable
{
    /**
     * The value of the primary key.
     */
    public function getKey(): mixed;

    /**
     * The document id field name (`id` by default).
     */
    public function getKeyName(): string;

    /**
     * The Elasticsearch index (or alias) backing this model.
     */
    public function getIndex(): string;

    /**
     * The name of the connection used by this model.
     */
    public function getConnectionName(): string;

    /**
     * Whether this model instance was loaded from Elasticsearch.
     */
    public function exists(): bool;

    /**
     * Read an attribute (cast, accessor aware).
     */
    public function getAttribute(string $key): mixed;

    /**
     * Write an attribute (mutator, cast aware).
     */
    public function setAttribute(string $key, mixed $value): static;

    /**
     * @param array<string, mixed> $attributes
     */
    public function fill(array $attributes): static;

    /**
     * @param array<string, mixed> $attributes
     */
    public function forceFill(array $attributes): static;

    /**
     * Insert or update the document.
     */
    public function save(): bool;

    /**
     * Update the given attributes and persist (instance level).
     *
     * @param array<string, mixed> $attributes
     */
    public function update(array $attributes = []): bool;

    /**
     * Delete the document from Elasticsearch.
     */
    public function delete(): bool;

    /**
     * Re-read the document from Elasticsearch.
     */
    public function refresh(): static;

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array;

    public function toJson(int $options = 0): string;

    /**
     * Start a brand new query for this model.
     */
    public function newQuery(): QueryBuilderInterface;
}
