<?php

declare(strict_types=1);

namespace EsOrm\Support;

use JsonException;

/**
 * Thin, exception-safe wrapper around the native JSON functions.
 */
final class Json
{
    /**
     * @throws JsonException
     */
    public static function encode(mixed $value, int $options = 0, int $depth = 512): string
    {
        return json_encode($value, $options | JSON_THROW_ON_ERROR, $depth);
    }

    /**
     * @throws JsonException
     */
    public static function decode(string $json, bool $assoc = true, int $depth = 512): mixed
    {
        return json_decode($json, $assoc, $depth, JSON_THROW_ON_ERROR);
    }
}
