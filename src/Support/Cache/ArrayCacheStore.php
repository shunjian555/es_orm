<?php

declare(strict_types=1);

namespace EsOrm\Support\Cache;

use EsOrm\Contracts\CacheStoreInterface;

/**
 * In-memory cache store used as the default query cache driver.
 *
 * Entries only live for the duration of the current PHP process, which makes
 * it a safe default (no stale data across requests) and ideal for tests.
 *
 * @phpstan-type Entry array{value: mixed, expires_at: int|null}
 */
final class ArrayCacheStore implements CacheStoreInterface
{
    /**
     * @var array<string, array{value: mixed, expires_at: int|null}>
     */
    private array $entries = [];

    public function get(string $key): mixed
    {
        if (!$this->has($key)) {
            $this->forget($key);

            return null;
        }

        return $this->entries[$key]['value'];
    }

    public function put(string $key, mixed $value, int $ttl): void
    {
        $this->entries[$key] = [
            'value' => $value,
            'expires_at' => $ttl > 0 ? time() + $ttl : null,
        ];
    }

    public function has(string $key): bool
    {
        if (!array_key_exists($key, $this->entries)) {
            return false;
        }

        $expiresAt = $this->entries[$key]['expires_at'];

        return $expiresAt === null || $expiresAt >= time();
    }

    public function forget(string $key): void
    {
        unset($this->entries[$key]);
    }

    public function flush(): void
    {
        $this->entries = [];
    }
}
