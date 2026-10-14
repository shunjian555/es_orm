<?php

declare(strict_types=1);

namespace EsOrm\Model\Casts;

use DateTimeImmutable;
use DateTimeInterface;
use Exception;

/**
 * Casts values to and from an Elasticsearch `date` field holding a timestamp.
 */
final class DatetimeCast implements CastInterface
{
    /**
     * Elasticsearch date storage format including the timezone offset.
     *
     * @var string
     */
    private const FORMAT = 'Y-m-d\TH:i:sP';

    /**
     * Convert a raw Elasticsearch value into a date/time object.
     *
     * @param mixed $value The raw value read from `_source`.
     *
     * @return mixed An immutable date/time object, or null when unavailable.
     */
    public function get(mixed $value): mixed
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof DateTimeInterface) {
            return DateTimeImmutable::createFromInterface($value);
        }

        if (is_int($value) || is_float($value)) {
            return (new DateTimeImmutable())->setTimestamp((int) $value);
        }

        if (is_string($value)) {
            try {
                return new DateTimeImmutable($value);
            } catch (Exception) {
                return null;
            }
        }

        return null;
    }

    /**
     * Convert a PHP value into an Elasticsearch-storable date/time string.
     *
     * @param mixed $value The PHP value supplied by the caller.
     *
     * @return mixed The formatted date/time string, or null when the input is null.
     */
    public function set(mixed $value): mixed
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof DateTimeInterface) {
            return $value->format(self::FORMAT);
        }

        if (is_int($value)) {
            return (new DateTimeImmutable())->setTimestamp($value)->format(self::FORMAT);
        }

        return $value;
    }
}
