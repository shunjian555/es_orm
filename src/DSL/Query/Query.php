<?php

declare(strict_types=1);

namespace EsOrm\DSL\Query;

use EsOrm\Contracts\DslInterface;

/**
 * Base class for every Elasticsearch query clause.
 *
 * Concrete queries are immutable value objects: all state is exposed through
 * constructor promoted, readonly properties and the only behaviour is
 * `toArray()`.
 */
abstract class Query implements DslInterface
{
    /**
     * @return array<string, mixed>
     */
    abstract public function toArray(): array;

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
