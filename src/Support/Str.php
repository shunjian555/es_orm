<?php

declare(strict_types=1);

namespace EsOrm\Support;

/**
 * Small string helpers used by the ORM (mapping snake_case to camelCase, ...).
 */
final class Str
{
    /**
     * Convert a value to `snake_case`.
     */
    public static function snake(string $value, string $delimiter = '_'): string
    {
        if ($value === '') {
            return $value;
        }

        $value = preg_replace('/\s+/u', '', ucwords($value)) ?? $value;
        $value = preg_replace('/(.)(?=[A-Z])/u', '$1' . $delimiter, $value) ?? $value;

        return mb_strtolower($value, 'UTF-8');
    }

    /**
     * Convert a value to `camelCase`.
     */
    public static function camel(string $value): string
    {
        return lcfirst(self::studly($value));
    }

    /**
     * Convert a value to `StudlyCase`.
     */
    public static function studly(string $value, string $delimiter = ' '): string
    {
        $value = ucwords(str_replace(['-', '_'], $delimiter, $value));

        return str_replace($delimiter, '', $value);
    }

    /**
     * Whether the haystack contains the given needle(s).
     *
     * @param array<array-key, string>|string $needles
     */
    public static function contains(string $haystack, array|string $needles): bool
    {
        foreach ((array) $needles as $needle) {
            if ($needle !== '' && str_contains($haystack, (string) $needle)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<array-key, string>|string $needles
     */
    public static function startsWith(string $haystack, array|string $needles): bool
    {
        foreach ((array) $needles as $needle) {
            if ($needle !== '' && str_starts_with($haystack, (string) $needle)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<array-key, string>|string $needles
     */
    public static function endsWith(string $haystack, array|string $needles): bool
    {
        foreach ((array) $needles as $needle) {
            if ($needle !== '' && str_ends_with($haystack, (string) $needle)) {
                return true;
            }
        }

        return false;
    }
}
