<?php

declare(strict_types=1);

namespace EsOrm\Relation;

use EsOrm\Model\Model;
use EsOrm\Model\ModelCollection;

/**
 * A single related document: the child whose foreign key points at the parent's
 * local key.
 */
final class HasOne extends Relation
{
    public function getResults(): ?Model
    {
        $key = $this->parent->getAttribute($this->localKey);

        if ($key === null) {
            return null;
        }

        return $this->getQuery()->where($this->foreignKey, $key)->first();
    }

    public function addEagerConstraints(array $models): void
    {
        $keys = self::keysOf($models, $this->localKey);

        if ($keys !== []) {
            $this->getQuery()->whereIn($this->foreignKey, $keys);
        }
    }

    public function match(array $models, ModelCollection $results): array
    {
        $dictionary = [];

        foreach ($results as $result) {
            if (!$result instanceof Model) {
                continue;
            }

            $key = $result->getAttribute($this->foreignKey);

            if ($key !== null) {
                $dictionary[(string) $key] = $result;
            }
        }

        foreach ($models as $model) {
            $key = $model->getAttribute($this->localKey);

            $model->setRelation(
                $this->assignName(),
                $key === null ? null : ($dictionary[(string) $key] ?? null),
            );
        }

        return $models;
    }

    /**
     * @param array<array-key, Model> $models
     * @return list<mixed>
     */
    private static function keysOf(array $models, string $attribute): array
    {
        $keys = [];

        foreach ($models as $model) {
            $value = $model->getAttribute($attribute);

            if ($value !== null) {
                $keys[(string) $value] = $value;
            }
        }

        return array_values($keys);
    }
}
