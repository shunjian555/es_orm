<?php

declare(strict_types=1);

namespace EsOrm\Model\Casts;

/**
 * Casts JSON payloads to and from Elasticsearch fields holding encoded data.
 */
final class JsonCast implements CastInterface
{
    /**
     * Convert a raw Elasticsearch value into a PHP value.
     *
     * JSON encoded strings are decoded, arrays and scalars are kept as-is.
     *
     * @param mixed $value The raw value read from `_source`.
     *
     * @return mixed The decoded PHP value, or null on invalid JSON / null input.
     */
    public function get(mixed $value): mixed
    {
        if ($value === null) {
            return null;
        }

        if (is_string($value)) {
            $decoded = json_decode($value, true);

            return json_last_error() === JSON_ERROR_NONE ? $decoded : null;
        }

        return $value;
    }

    /**
     * Convert a PHP value into an Elasticsearch-storable value.
     *
     * Arrays and scalars are kept as-is; strings are decoded when they hold
     * valid JSON, otherwise the raw string is preserved.
     *
     * @param mixed $value The PHP value supplied by the caller.
     *
     * @return mixed The value prepared for storage, or null when the input is null.
     */
    public function set(mixed $value): mixed
    {
        if ($value === null) {
            return null;
        }

        if (is_array($value)) {
            return $value;
        }

        if (is_string($value)) {
            $decoded = json_decode($value, true);

            return json_last_error() === JSON_ERROR_NONE ? $decoded : $value;
        }

        return $value;
    }
}
