<?php

declare(strict_types=1);

namespace EsOrm\Relation;

use EsOrm\Model\Model;
use EsOrm\Model\ModelCollection;

/**
 * The inverse of {@see HasMany}/{@see HasOne}: the parent holds a foreign key
 * that points at the owner key of the related document.
 *
 * Note that the inherited `$foreignKey` is the attribute on *this* (the owner)
 * model, while `$localKey` holds the key name on the related model.
 */
final class BelongsTo extends Relation
{
    public function getResults(): ?Model
    {
        $key = $this->parent->getAttribute($this->foreignKey);

        if ($key === null) {
            return null;
        }

        return $this->getQuery()->where($this->localKey, $key)->first();
    }

    public function addEagerConstraints(array $models): void
    {
        $keys = self::keysOf($models, $this->foreignKey);

        if ($keys !== []) {
            $this->getQuery()->whereIn($this->localKey, $keys);
        }
    }

    public function match(array $models, ModelCollection $results): array
    {
        $dictionary = [];

        foreach ($results as $result) {
            if (!$result instanceof Model) {
                continue;
            }

            $key = $result->getAttribute($this->localKey);

            if ($key !== null) {
                $dictionary[(string) $key] = $result;
            }
        }

        foreach ($models as $model) {
            $key = $model->getAttribute($this->foreignKey);

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
