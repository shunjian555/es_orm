<?php

declare(strict_types=1);

namespace EsOrm\Model\Casts;

use JsonSerializable;
use stdClass;

/**
 * Casts object structures to and from the Elasticsearch `object` field type.
 */
final class ObjectCast implements CastInterface
{
    /**
     * Convert a raw Elasticsearch value into an object.
     *
     * JSON encoded strings are decoded, arrays are converted to objects and
     * scalars are wrapped into an object exposing the `value` property.
     *
     * @param mixed $value The raw value read from `_source`.
     *
     * @return mixed The object value, or null on invalid JSON / null input.
     */
    public function get(mixed $value): mixed
    {
        if ($value === null) {
            return null;
        }

        if (is_string($value)) {
            $decoded = json_decode($value);

            return json_last_error() === JSON_ERROR_NONE ? $decoded : null;
        }

        if (is_array($value)) {
            return (object) $value;
        }

        if (is_object($value)) {
            return $value;
        }

        return (object) ['value' => $value];
    }

    /**
     * Convert a PHP value into an Elasticsearch-storable object payload.
     *
     * Objects are converted to arrays, arrays are kept as-is and JSON encoded
     * strings holding an object are decoded into an array.
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

        if ($value instanceof JsonSerializable || $value instanceof stdClass) {
            return (array) $value;
        }

        if (is_array($value)) {
            return $value;
        }

        if (is_string($value)) {
            $decoded = json_decode($value, true);

            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                return $decoded;
            }
        }

        return (array) $value;
    }
}
