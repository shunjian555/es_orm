<?php

declare(strict_types=1);

namespace EsOrm\Contracts;

/**
 * Pluggable cache store used by the query cache.
 *
 * The default driver is {@see \EsOrm\Support\Cache\ArrayCacheStore}; Laravel
 * users can plug in the framework cache through
 * {@see \EsOrm\Laravel\Cache\LaravelCacheStore}.
 */
interface CacheStoreInterface
{
    /**
     * Read a cached value. Returns null on a cache miss.
     */
    public function get(string $key): mixed;

    /**
     * Store a value for the given number of seconds.
     */
    public function put(string $key, mixed $value, int $ttl): void;

    public function has(string $key): bool;

    public function forget(string $key): void;

    /**
     * Remove every entry of this store.
     */
    public function flush(): void;
}
