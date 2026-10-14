<?php

declare(strict_types=1);

namespace EsOrm\Support;

use ArrayAccess;

/**
 * Dot-notation aware configuration repository.
 *
 * The core package is framework agnostic: a Laravel application simply wraps
 * its `config('es_orm')` array into this object before handing it over.
 *
 * @implements ArrayAccess<string, mixed>
 */
final class Config implements ArrayAccess
{
    /**
     * @param array<string, mixed> $items
     */
    public function __construct(private array $items = [])
    {
    }

    /**
     * @param array<string, mixed> $items
     */
    public static function fromArray(array $items): self
    {
        return new self($items);
    }

    public static function fromFile(string $path): self
    {
        if (!is_file($path)) {
            return new self();
        }

        $items = require $path;

        return new self(is_array($items) ? $items : []);
    }

    /**
     * Read a configuration value using dot notation.
     */
    public function get(string $key, mixed $default = null): mixed
    {
        return Arr::get($this->items, $key, $default);
    }

    public function set(string $key, mixed $value): void
    {
        Arr::set($this->items, $key, $value);
    }

    public function has(string $key): bool
    {
        if (Arr::has($this->items, $key)) {
            return true;
        }

        return Arr::get($this->items, $key) !== null;
    }

    public function forget(string $key): void
    {
        Arr::forget($this->items, $key);
    }

    /**
     * @return array<string, mixed>
     */
    public function all(): array
    {
        return $this->items;
    }

    public function offsetExists(mixed $offset): bool
    {
        return $this->has((string) $offset);
    }

    public function offsetGet(mixed $offset): mixed
    {
        return $this->get((string) $offset);
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        $this->set((string) $offset, $value);
    }

    public function offsetUnset(mixed $offset): void
    {
        $this->forget((string) $offset);
    }
}
