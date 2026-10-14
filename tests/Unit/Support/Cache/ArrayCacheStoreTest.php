<?php

declare(strict_types=1);

namespace EsOrm\Tests\Unit\Support\Cache;

use EsOrm\Contracts\CacheStoreInterface;
use EsOrm\Support\Cache\ArrayCacheStore;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

final class ArrayCacheStoreTest extends TestCase
{
    public function test_it_implements_the_cache_store_contract(): void
    {
        self::assertInstanceOf(CacheStoreInterface::class, new ArrayCacheStore());
    }

    public function test_it_stores_and_reads_a_value(): void
    {
        $store = new ArrayCacheStore();
        $store->put('key', ['a' => 1], 60);

        self::assertTrue($store->has('key'));
        self::assertSame(['a' => 1], $store->get('key'));
    }

    public function test_it_returns_null_and_has_is_false_for_a_miss(): void
    {
        $store = new ArrayCacheStore();

        self::assertNull($store->get('missing'));
        self::assertFalse($store->has('missing'));
    }

    public function test_a_zero_ttl_means_no_expiration(): void
    {
        $store = new ArrayCacheStore();
        $store->put('key', 'value', 0);

        self::assertTrue($store->has('key'));
        self::assertSame('value', $store->get('key'));
    }

    public function test_a_negative_ttl_means_no_expiration(): void
    {
        $store = new ArrayCacheStore();
        $store->put('key', 'value', -10);

        self::assertTrue($store->has('key'));
    }

    public function test_it_forgets_a_key(): void
    {
        $store = new ArrayCacheStore();
        $store->put('key', 'value', 60);
        $store->forget('key');

        self::assertFalse($store->has('key'));
        self::assertNull($store->get('key'));
    }

    public function test_it_flushes_every_entry(): void
    {
        $store = new ArrayCacheStore();
        $store->put('a', 1, 60);
        $store->put('b', 2, 60);
        $store->flush();

        self::assertFalse($store->has('a'));
        self::assertFalse($store->has('b'));
    }

    public function test_a_key_past_its_expiration_is_missed(): void
    {
        $store = new ArrayCacheStore();
        $store->put('key', 'value', 60);

        $this->expire($store, 'key');

        self::assertFalse($store->has('key'));
    }

    public function test_reading_an_expired_key_removes_it(): void
    {
        $store = new ArrayCacheStore();
        $store->put('key', 'value', 60);
        $this->expire($store, 'key');

        self::assertNull($store->get('key'));
        self::assertFalse($store->has('key'));
    }

    /**
     * Force the given key to be already expired by moving its TTL into the past.
     */
    private function expire(ArrayCacheStore $store, string $key): void
    {
        $property = new ReflectionProperty(ArrayCacheStore::class, 'entries');
        $entries = $property->getValue($store);
        $entries[$key]['expires_at'] = time() - 1;
        $property->setValue($store, $entries);
    }
}
