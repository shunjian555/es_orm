<?php

declare(strict_types=1);

namespace EsOrm\Model\Casts;

/**
 * Casts values to and from the Elasticsearch `integer` / `long` field type.
 */
final class IntegerCast implements CastInterface
{
    /**
     * Convert a raw Elasticsearch value into an integer.
     *
     * @param mixed $value The raw value read from `_source`.
     *
     * @return mixed The integer value, or null when the input is null.
     */
    public function get(mixed $value): mixed
    {
        return $value === null ? null : (int) $value;
    }

    /**
     * Convert a PHP value into an Elasticsearch-storable integer.
     *
     * @param mixed $value The PHP value supplied by the caller.
     *
     * @return mixed The integer value, or null when the input is null.
     */
    public function set(mixed $value): mixed
    {
        return $value === null ? null : (int) $value;
    }
}
