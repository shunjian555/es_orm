<?php

declare(strict_types=1);

namespace EsOrm\Migration;

use EsOrm\Contracts\MappingInterface;
use EsOrm\Index\IndexManager;
use EsOrm\Model\Model;

/**
 * Base class for an index migration, inspired by Laravel migrations.
 *
 * ```php
 * final class CreateArticlesIndex extends IndexMigration
 * {
 *     public function up(): void
 *     {
 *         $this->createIndex('articles', Mapping::create('articles')->keyword('status'));
 *     }
 *
 *     public function down(): void
 *     {
 *         $this->deleteIndex('articles');
 *     }
 * }
 * ```
 *
 * The {@see Migrator} injects the {@see IndexManager} before running the
 * migration; when a migration is used standalone it falls back to the model
 * connection manager.
 */
abstract class IndexMigration
{
    private ?IndexManager $manager = null;

    /**
     * Apply the migration.
     */
    abstract public function up(): void;

    /**
     * Revert the migration.
     */
    abstract public function down(): void;

    /**
     * Inject the index manager used by the migration helpers.
     */
    public function withManager(IndexManager $manager): static
    {
        $this->manager = $manager;

        return $this;
    }

    /**
     * The index manager bound to this migration.
     */
    public function manager(): IndexManager
    {
        return $this->manager ??= IndexManager::on(Model::connectionManager());
    }

    /**
     * Create an index, optionally applying a mapping.
     *
     * @param array<string, mixed> $settings
     */
    protected function createIndex(string $index, ?MappingInterface $mapping = null, array $settings = []): bool
    {
        return $this->manager()->createIndex($index, $mapping, $settings);
    }

    /**
     * Delete an index.
     */
    protected function deleteIndex(string $index): bool
    {
        return $this->manager()->deleteIndex($index);
    }

    /**
     * Add or update the mapping of an existing index.
     *
     * @param MappingInterface|array<string, mixed> $mapping
     */
    protected function putMapping(string $index, MappingInterface|array $mapping): bool
    {
        return $this->manager()->putMapping($index, $mapping);
    }

    /**
     * Whether an index exists.
     */
    protected function indexExists(string $index): bool
    {
        return $this->manager()->indexExists($index);
    }

    /**
     * Refresh an index so recent writes become searchable.
     */
    protected function refresh(string $index): bool
    {
        return $this->manager()->refresh($index);
    }
}
