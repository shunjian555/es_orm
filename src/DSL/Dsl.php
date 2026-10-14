<?php

declare(strict_types=1);

namespace EsOrm\DSL;

use EsOrm\Contracts\DslInterface;

/**
 * Helpers used by the query compiler to normalise DSL fragments.
 *
 * The ORM accepts both DSL objects ({@see DslInterface}) and raw arrays
 * everywhere a clause is expected, so a single normalisation point keeps the
 * compiler code small and predictable.
 */
final class Dsl
{
    /**
     * Normalise a single clause into an Elasticsearch array structure.
     *
     * @param DslInterface|array<array-key, mixed> $clause
     * @return array<string, mixed>
     */
    public static function clause(DslInterface|array $clause): array
    {
        if ($clause instanceof DslInterface) {
            return $clause->toArray();
        }

        /** @var array<string, mixed> $clause */
        return $clause;
    }

    /**
     * Normalise a list of clauses.
     *
     * @param iterable<DslInterface|array<array-key, mixed>> $clauses
     * @return list<array<string, mixed>>
     */
    public static function clauses(iterable $clauses): array
    {
        $normalised = [];

        foreach ($clauses as $clause) {
            $normalised[] = self::clause($clause);
        }

        return $normalised;
    }

    /**
     * Convert an empty array to a JSON object so Elasticsearch receives `{}`
     * instead of `[]` where an object is required (e.g. `reverse_nested`).
     *
     * @param array<array-key, mixed> $value
     * @return array<array-key, mixed>|object
     */
    public static function toObjectWhenEmpty(array $value): array|object
    {
        return $value === [] ? (object) [] : $value;
    }
}
