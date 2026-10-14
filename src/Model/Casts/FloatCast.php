<?php

declare(strict_types=1);

namespace EsOrm\Model\Casts;

/**
 * Casts values to and from the Elasticsearch `float` / `double` field types.
 */
final class FloatCast implements CastInterface
{
    /**
     * Convert a raw Elasticsearch value into a float.
     *
     * @param mixed $value The raw value read from `_source`.
     *
     * @return mixed The float value, or null when the input is null.
     */
    public function get(mixed $value): mixed
    {
        return $value === null ? null : (float) $value;
    }

    /**
     * Convert a PHP value into an Elasticsearch-storable float.
     *
     * @param mixed $value The PHP value supplied by the caller.
     *
     * @return mixed The float value, or null when the input is null.
     */
    public function set(mixed $value): mixed
    {
        return $value === null ? null : (float) $value;
    }
}
