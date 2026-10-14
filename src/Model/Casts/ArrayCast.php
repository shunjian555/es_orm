<?php

declare(strict_types=1);

namespace EsOrm\Model\Casts;

/**
 * Casts values to and from an Elasticsearch `array` (multi-value) field.
 */
final class ArrayCast implements CastInterface
{
    /**
     * Convert a raw Elasticsearch value into an array.
     *
     * JSON encoded strings are decoded, arrays are kept as-is and every other
     * value is wrapped into a single-element array.
     *
     * @param mixed $value The raw value read from `_source`.
     *
     * @return mixed The array value, or null when the input is null.
     */
    public function get(mixed $value): mixed
    {
        if ($value === null) {
            return null;
        }

        if (is_array($value)) {
            return $value;
        }

        if (is_string($value)) {
            $decoded = json_decode($value, true);

            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                return $decoded;
            }

            return [$value];
        }

        return (array) $value;
    }

    /**
     * Convert a PHP value into an Elasticsearch-storable array.
     *
     * @param mixed $value The PHP value supplied by the caller.
     *
     * @return mixed The array value, or null when the input is null.
     */
    public function set(mixed $value): mixed
    {
        if ($value === null) {
            return null;
        }

        if (is_array($value)) {
            return $value;
        }

        return (array) $value;
    }
}
