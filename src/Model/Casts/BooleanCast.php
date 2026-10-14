<?php

declare(strict_types=1);

namespace EsOrm\Model\Casts;

/**
 * Casts values to and from the Elasticsearch `boolean` field type.
 */
final class BooleanCast implements CastInterface
{
    /**
     * Truthy string representations accepted from stored documents.
     *
     * @var array<int, string>
     */
    private const TRUTHY = ['1', 'true', 'yes', 'on'];

    /**
     * Convert a raw Elasticsearch value into a boolean.
     *
     * @param mixed $value The raw value read from `_source`.
     *
     * @return mixed The boolean value, or null when the input is null.
     */
    public function get(mixed $value): mixed
    {
        if ($value === null) {
            return null;
        }

        if ($value === true || $value === false) {
            return $value;
        }

        if (is_string($value) && in_array(strtolower($value), self::TRUTHY, true)) {
            return true;
        }

        return (bool) $value;
    }

    /**
     * Convert a PHP value into an Elasticsearch-storable boolean.
     *
     * @param mixed $value The PHP value supplied by the caller.
     *
     * @return mixed The boolean value, or null when the input is null.
     */
    public function set(mixed $value): mixed
    {
        return $value === null ? null : (bool) $value;
    }
}
