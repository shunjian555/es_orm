<?php

declare(strict_types=1);

namespace EsOrm\Model\Casts;

use EsOrm\Exceptions\MappingException;

/**
 * Resolves a cast type name (or an existing cast instance) into a concrete
 * {@see CastInterface} implementation.
 */
final class CastFactory
{
    /**
     * Supported cast type aliases mapped to their concrete implementations.
     *
     * @var array<string, class-string<CastInterface>>
     */
    private const CASTS = [
        'int' => IntegerCast::class,
        'integer' => IntegerCast::class,
        'float' => FloatCast::class,
        'double' => FloatCast::class,
        'decimal' => FloatCast::class,
        'bool' => BooleanCast::class,
        'boolean' => BooleanCast::class,
        'string' => StringCast::class,
        'array' => ArrayCast::class,
        'json' => JsonCast::class,
        'object' => ObjectCast::class,
        'map' => ObjectCast::class,
        'datetime' => DatetimeCast::class,
        'date_time' => DatetimeCast::class,
        'timestamp' => DatetimeCast::class,
        'date' => DateCast::class,
    ];

    /**
     * Resolve the given cast identifier into a cast instance.
     *
     * @param string|CastInterface $cast A cast instance or a cast type name.
     *
     * @return CastInterface The resolved cast instance.
     *
     * @throws MappingException When the cast type is not supported.
     */
    public static function make(string|CastInterface $cast): CastInterface
    {
        if ($cast instanceof CastInterface) {
            return $cast;
        }

        $type = strtolower($cast);

        if (! isset(self::CASTS[$type])) {
            throw new MappingException(sprintf('Unsupported cast type [%s].', $type));
        }

        $class = self::CASTS[$type];

        return new $class();
    }

    /**
     * Determine whether the given cast type is supported.
     *
     * @param string $type The cast type name.
     *
     * @return bool True when a cast implementation exists for the type.
     */
    public static function supports(string $type): bool
    {
        return isset(self::CASTS[strtolower($type)]);
    }

    /**
     * Read a stored value through the given cast.
     *
     * @param mixed                $value The raw value read from `_source`.
     * @param string|CastInterface $cast  A cast instance or a cast type name.
     *
     * @return mixed The hydrated PHP value.
     */
    public static function get(mixed $value, string|CastInterface $cast): mixed
    {
        return self::make($cast)->get($value);
    }

    /**
     * Prepare a PHP value for storage through the given cast.
     *
     * @param mixed                $value The PHP value supplied by the caller.
     * @param string|CastInterface $cast  A cast instance or a cast type name.
     *
     * @return mixed The value prepared for storage in Elasticsearch.
     */
    public static function set(mixed $value, string|CastInterface $cast): mixed
    {
        return self::make($cast)->set($value);
    }
}
