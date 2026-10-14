<?php

declare(strict_types=1);

namespace EsOrm\Relation;

use EsOrm\Model\Model;
use EsOrm\Model\ModelCollection;
use EsOrm\Query\Builder;

/**
 * Base class of the (deliberately limited) relationship layer.
 *
 * Relationships in an Elasticsearch ORM are *not* SQL joins: they are a batch
 * aware convenience layer on top of several searches, designed so that eager
 * loading with `with()`/`load()` never triggers an N+1 pattern.
 */
abstract class Relation
{
    protected ?Builder $query = null;

    protected ?string $relationName = null;

    public function __construct(
        protected readonly Model $parent,
        protected readonly Model $related,
        protected readonly string $foreignKey,
        protected readonly string $localKey,
    ) {
    }

    /**
     * Resolve the relation for a single (already loaded) parent model.
     *
     * @return Model|ModelCollection|null
     */
    abstract public function getResults(): Model|ModelCollection|null;

    /**
     * Add the constraints required to eager load this relation for many
     * parents at once.
     *
     * @param array<array-key, Model> $models
     */
    abstract public function addEagerConstraints(array $models): void;

    /**
     * Assign the eager loaded results onto their parents.
     *
     * @param array<array-key, Model> $models
     * @return array<array-key, Model>
     */
    abstract public function match(array $models, ModelCollection $results): array;

    /**
     * A fresh query builder for the related model.
     */
    public function getQuery(): Builder
    {
        return $this->query ??= $this->related->newQuery();
    }

    /**
     * Execute the eager loading query.
     */
    public function getEagerResults(): ModelCollection
    {
        return $this->getQuery()->get();
    }

    public function getParent(): Model
    {
        return $this->parent;
    }

    public function getRelated(): Model
    {
        return $this->related;
    }

    public function getForeignKeyName(): string
    {
        return $this->foreignKey;
    }

    public function getLocalKeyName(): string
    {
        return $this->localKey;
    }

    public function setRelationName(string $name): static
    {
        $this->relationName = $name;

        return $this;
    }

    public function getRelationName(): ?string
    {
        return $this->relationName;
    }

    /**
     * The attribute name under which eager loaded results are assigned.
     */
    protected function assignName(): string
    {
        return $this->relationName ?? $this->relationNameFromClass();
    }

    private function relationNameFromClass(): string
    {
        $short = substr(static::class, (int) strrpos(static::class, '\\') + 1);

        return lcfirst($short);
    }
}
