<?php

declare(strict_types=1);

namespace EsOrm\Model\Casts;

/**
 * Casts values to and from the Elasticsearch `keyword` / `text` field type.
 */
final class StringCast implements CastInterface
{
    /**
     * Convert a raw Elasticsearch value into a string.
     *
     * @param mixed $value The raw value read from `_source`.
     *
     * @return mixed The string value, or null when the input is null.
     */
    public function get(mixed $value): mixed
    {
        return $value === null ? null : (string) $value;
    }

    /**
     * Convert a PHP value into an Elasticsearch-storable string.
     *
     * @param mixed $value The PHP value supplied by the caller.
     *
     * @return mixed The string value, or null when the input is null.
     */
    public function set(mixed $value): mixed
    {
        return $value === null ? null : (string) $value;
    }
}
