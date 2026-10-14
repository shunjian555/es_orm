<?php

declare(strict_types=1);

namespace EsOrm\Support;

/**
 * Minimal array helpers, independent from Laravel.
 *
 * Only the helpers the ORM actually needs are implemented; keep additions
 * purposeful rather than mirroring `Illuminate\Support\Arr` wholesale.
 */
final class Arr
{
    /**
     * Wrap a value into an array unless it already is one.
     *
     * @return array<array-key, mixed>
     */
    public static function wrap(mixed $value): array
    {
        if ($value === null) {
            return [];
        }

        return is_array($value) ? $value : [$value];
    }

    /**
     * Get an item using dot notation.
     *
     * @param array<array-key, mixed> $array
     */
    public static function get(array $array, string|int|null $key, mixed $default = null): mixed
    {
        if ($key === null) {
            return $array;
        }

        if (array_key_exists($key, $array)) {
            return $array[$key];
        }

        if (!is_string($key) || !str_contains($key, '.')) {
            return $default;
        }

        $value = $array;

        foreach (explode('.', $key) as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return $default;
            }

            $value = $value[$segment];
        }

        return $value;
    }

    /**
     * Set an item using dot notation, creating intermediate arrays.
     *
     * @param array<array-key, mixed> $array
     */
    public static function set(array &$array, string|int|null $key, mixed $value): void
    {
        if ($key === null) {
            return;
        }

        if (!is_string($key) || !str_contains($key, '.')) {
            $array[$key] = $value;

            return;
        }

        $segments = explode('.', $key);
        $target = &$array;

        while ($segments !== []) {
            $segment = array_shift($segments);

            if ($segments === []) {
                $target[$segment] = $value;

                return;
            }

            if (!isset($target[$segment]) || !is_array($target[$segment])) {
                $target[$segment] = [];
            }

            $target = &$target[$segment];
        }
    }

    /**
     * Determine whether one key (or every key of an array of keys) exists.
     *
     * @param array<array-key, mixed> $array
     * @param array<array-key, string|int>|string|int $keys
     */
    public static function has(array $array, array|string|int $keys): bool
    {
        if (!is_array($keys)) {
            $keys = [$keys];
        }

        foreach ($keys as $key) {
            if (!array_key_exists($key, $array)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Remove an item using dot notation.
     *
     * @param array<array-key, mixed> $array
     */
    public static function forget(array &$array, string|int $key): void
    {
        if (!is_string($key) || !str_contains($key, '.')) {
            unset($array[$key]);

            return;
        }

        $segments = explode('.', $key);
        $last = array_pop($segments);
        $target = &$array;

        foreach ($segments as $segment) {
            if (!isset($target[$segment]) || !is_array($target[$segment])) {
                return;
            }

            $target = &$target[$segment];
        }

        unset($target[$last]);
    }

    /**
     * Keep only the given keys.
     *
     * @param array<array-key, mixed> $array
     * @param array<array-key, string|int>|string|int $keys
     * @return array<array-key, mixed>
     */
    public static function only(array $array, array|string|int $keys): array
    {
        if (!is_array($keys)) {
            $keys = [$keys];
        }

        return array_intersect_key($array, array_flip($keys));
    }

    /**
     * Remove the given keys.
     *
     * @param array<array-key, mixed> $array
     * @param array<array-key, string|int>|string|int $keys
     * @return array<array-key, mixed>
     */
    public static function except(array $array, array|string|int $keys): array
    {
        if (!is_array($keys)) {
            $keys = [$keys];
        }

        return array_diff_key($array, array_flip($keys));
    }

    /**
     * Whether the given array is associative (i.e. not a list).
     *
     * @param array<array-key, mixed> $array
     */
    public static function isAssoc(array $array): bool
    {
        if ($array === []) {
            return false;
        }

        return array_keys($array) !== range(0, count($array) - 1);
    }
}
