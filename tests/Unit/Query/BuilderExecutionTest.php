<?php

declare(strict_types=1);

namespace EsOrm\Tests\Unit\Query;

use EsOrm\Support\Cache\ArrayCacheStore;
use EsOrm\Tests\Fixtures\User;
use EsOrm\Tests\TestCase;

/**
 * Covers execution of the builder against the (fake) client.
 */
final class BuilderExecutionTest extends TestCase
{
    public function test_get_sends_the_compiled_body_and_default_index(): void
    {
        // Arrange
        $this->client->setResponse('search', [
            'hits' => ['total' => ['value' => 1], 'hits' => [['_id' => '1', '_source' => ['name' => 'Tom']]]],
        ]);

        // Act
        User::query()->where('status', 'active')->get();

        // Assert
        $params = $this->client->lastParams('search');
        self::assertSame('users', $params['index']);
        self::assertEquals(['bool' => ['must' => [['term' => ['status' => 'active']]]]], $params['body']['query']);
    }

    public function test_get_hydrates_the_hits_into_models(): void
    {
        // Arrange
        $this->client->setResponse('search', [
            'hits' => [
                'total' => ['value' => 2],
                'hits' => [
                    ['_id' => '1', '_source' => ['name' => 'Tom', 'age' => 30]],
                    ['_id' => '2', '_source' => ['name' => 'Jane', 'age' => 25]],
                ],
            ],
        ]);

        // Act
        $collection = User::query()->get();

        // Assert
        self::assertCount(2, $collection);
        self::assertSame('Tom', $collection->first()->name);
        self::assertSame(30, $collection->first()->age);
        self::assertSame('2', $collection->last()->getKey());
    }

    public function test_get_sends_the_overridden_index(): void
    {
        // Arrange
        $this->client->setResponse('search', ['hits' => ['total' => ['value' => 0], 'hits' => []]]);

        // Act
        User::query()->from('users_archive')->get();

        // Assert
        self::assertSame('users_archive', $this->client->lastParams('search')['index']);
    }

    public function test_search_returns_the_full_result_with_metadata(): void
    {
        // Arrange
        $this->client->setResponse('search', [
            'took' => 3,
            'timed_out' => false,
            'hits' => ['total' => ['value' => 5], 'hits' => [['_id' => '1', '_source' => ['name' => 'A']]]],
            'aggregations' => ['by_status' => ['buckets' => []]],
        ]);

        // Act
        $result = User::query()->search();

        // Assert
        self::assertSame(5, $result->total());
        self::assertSame(3, $result->took());
        self::assertEquals(['by_status' => ['buckets' => []]], $result->aggregations());
        self::assertCount(1, $result->hits());
    }

    public function test_first_limits_the_request_to_a_single_record(): void
    {
        // Arrange
        $this->client->setResponse('search', [
            'hits' => ['total' => ['value' => 1], 'hits' => [['_id' => '1', '_source' => ['name' => 'Tom']]]],
        ]);

        // Act
        $model = User::query()->first();

        // Assert
        self::assertSame('Tom', $model->name);
        self::assertSame(1, $this->client->lastParams('search')['body']['size']);
    }

    public function test_count_sends_a_count_request_with_the_query_only(): void
    {
        // Arrange
        $this->client->setResponse('count', ['count' => 7]);

        // Act
        $count = User::query()->where('status', 'active')->count();

        // Assert
        self::assertSame(7, $count);
        $params = $this->client->lastParams('count');
        self::assertSame('users', $params['index']);
        self::assertEquals(['query' => ['bool' => ['must' => [['term' => ['status' => 'active']]]]]], $params['body']);
    }

    public function test_each_uses_scroll_and_clears_the_scroll(): void
    {
        // Arrange
        $this->client->setResponse('search', [
            '_scroll_id' => 'scroll-1',
            'hits' => [
                'total' => ['value' => 2],
                'hits' => [
                    ['_id' => '1', '_source' => ['name' => 'A']],
                    ['_id' => '2', '_source' => ['name' => 'B']],
                ],
            ],
        ]);
        $this->client->setResponse('scroll', [
            '_scroll_id' => 'scroll-1',
            'hits' => ['total' => ['value' => 2], 'hits' => []],
        ]);

        $collected = [];

        // Act
        $result = User::query()->each(static function ($model) use (&$collected): void {
            $collected[] = $model->name;
        }, 1000);

        // Assert
        self::assertTrue($result);
        self::assertSame(['A', 'B'], $collected);
        self::assertSame(1, $this->client->callCount('search'));
        self::assertSame(1, $this->client->callCount('scroll'));
        self::assertSame(1, $this->client->callCount('clearScroll'));
        self::assertSame(['scroll_id' => ['scroll-1']], $this->client->lastParams('clearScroll'));
        self::assertSame('1m', $this->client->lastParams('search')['scroll']);
    }

    public function test_each_with_search_after_opens_and_closes_a_point_in_time(): void
    {
        // Arrange
        $this->client->setResponse('openPointInTime', ['id' => 'pit-1']);
        $this->client->setResponse('search', [
            'hits' => ['total' => ['value' => 1], 'hits' => [['_id' => '1', '_source' => ['name' => 'A'], 'sort' => [1]]]],
        ]);

        $collected = [];

        // Act
        $result = User::query()->orderBy('id')->searchAfter([0])->each(static function ($model) use (&$collected): void {
            $collected[] = $model->name;
        }, 2);

        // Assert
        self::assertTrue($result);
        self::assertSame(['A'], $collected);
        self::assertSame(1, $this->client->callCount('openPointInTime'));
        self::assertSame(['index' => 'users', 'keep_alive' => '1m'], $this->client->lastParams('openPointInTime'));
        self::assertArrayNotHasKey('index', $this->client->lastParams('search'));
        self::assertSame([0], $this->client->lastParams('search')['body']['search_after']);
        self::assertEquals(['id' => 'pit-1', 'keep_alive' => '1m'], $this->client->lastParams('search')['body']['pit']);
        self::assertSame(1, $this->client->callCount('closePointInTime'));
        self::assertSame(['body' => ['id' => 'pit-1']], $this->client->lastParams('closePointInTime'));
    }

    public function test_each_stops_when_the_callback_returns_false_and_still_clears_the_scroll(): void
    {
        // Arrange
        $this->client->setResponse('search', [
            '_scroll_id' => 'scroll-1',
            'hits' => [
                'total' => ['value' => 2],
                'hits' => [
                    ['_id' => '1', '_source' => ['name' => 'A']],
                    ['_id' => '2', '_source' => ['name' => 'B']],
                ],
            ],
        ]);

        $collected = [];

        // Act
        $result = User::query()->each(static function ($model) use (&$collected): bool {
            $collected[] = $model->name;

            return false;
        }, 1000);

        // Assert
        self::assertFalse($result);
        self::assertSame(['A'], $collected);
        self::assertSame(0, $this->client->callCount('scroll'));
        self::assertSame(1, $this->client->callCount('clearScroll'));
    }

    public function test_cache_opts_in_and_serves_the_second_call_from_the_store(): void
    {
        // Arrange
        $this->manager = $this->makeManager($this->client, [
            'cache' => ['driver' => ArrayCacheStore::class, 'enabled' => true],
        ]);
        \EsOrm\Model\Model::setConnectionManager($this->manager);

        $this->client->setResponse('search', [
            'hits' => ['total' => ['value' => 1], 'hits' => [['_id' => '1', '_source' => ['name' => 'Tom']]]],
        ]);

        $query = User::query()->where('status', 'active')->cache(60);

        // Act
        $first = $query->get();
        $second = $query->get();

        // Assert
        self::assertSame(1, $first->count());
        self::assertSame(1, $second->count());
        self::assertSame(1, $this->client->callCount('search'));
    }
}
