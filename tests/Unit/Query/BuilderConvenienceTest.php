<?php

declare(strict_types=1);

namespace EsOrm\Tests\Unit\Query;

use EsOrm\Exceptions\ModelNotFoundException;
use EsOrm\Exceptions\MultipleRecordsFoundException;
use EsOrm\Tests\Fixtures\User;
use EsOrm\Tests\TestCase;

/**
 * Covers the convenience execution APIs: exists(), sole(), the avg/sum/min/max
 * metric shortcuts, deleteByQuery() and the firstOrCreate family.
 */
final class BuilderConvenienceTest extends TestCase
{
    /* ------------------------------------------------------------------
     |  exists()
     | ------------------------------------------------------------------ */

    public function test_exists_returns_true_when_documents_match(): void
    {
        // Arrange
        $this->client->setResponse('count', ['count' => 7]);

        // Act
        $exists = User::query()->where('status', 'active')->exists();

        // Assert
        self::assertTrue($exists);
        self::assertSame('users', $this->client->lastParams('count')['index']);
    }

    public function test_exists_returns_false_when_nothing_matches(): void
    {
        // Act
        $exists = User::query()->where('status', 'ghost')->exists();

        // Assert
        self::assertFalse($exists);
    }

    /* ------------------------------------------------------------------
     |  sole()
     | ------------------------------------------------------------------ */

    public function test_sole_returns_the_single_matching_document(): void
    {
        // Arrange
        $this->client->setResponse('search', [
            'hits' => ['total' => ['value' => 1], 'hits' => [['_id' => '1', '_source' => ['name' => 'Tom']]]],
        ]);

        // Act
        $model = User::query()->where('email', 'tom@example.com')->sole();

        // Assert
        self::assertSame('Tom', $model->name);
        self::assertSame(2, $this->client->lastParams('search')['body']['size']);
    }

    public function test_sole_throws_when_nothing_matches(): void
    {
        $this->expectException(ModelNotFoundException::class);

        User::query()->where('email', 'ghost@example.com')->sole();
    }

    public function test_sole_throws_when_several_documents_match(): void
    {
        // Arrange
        $this->client->setResponse('search', [
            'hits' => [
                'total' => ['value' => 2],
                'hits' => [
                    ['_id' => '1', '_source' => ['name' => 'Tom']],
                    ['_id' => '2', '_source' => ['name' => 'Jane']],
                ],
            ],
        ]);

        $this->expectException(MultipleRecordsFoundException::class);

        // Act
        User::query()->where('status', 'active')->sole();
    }

    /* ------------------------------------------------------------------
     |  avg / sum / min / max
     | ------------------------------------------------------------------ */

    public function test_avg_runs_a_size_zero_metric_aggregation(): void
    {
        // Arrange
        $this->client->setResponse('search', [
            'hits' => ['total' => ['value' => 2], 'hits' => []],
            'aggregations' => ['metric' => ['value' => 27.5]],
        ]);

        // Act
        $value = User::query()->where('status', 'active')->avg('age');

        // Assert
        self::assertSame(27.5, $value);

        $params = $this->client->lastParams('search');
        self::assertSame(0, $params['body']['size']);
        self::assertEquals(['avg' => ['field' => 'age']], $params['body']['aggs']['metric']);
    }

    public function test_sum_returns_the_aggregated_value(): void
    {
        // Arrange
        $this->client->setResponse('search', [
            'hits' => ['total' => ['value' => 3], 'hits' => []],
            'aggregations' => ['metric' => ['value' => 4500]],
        ]);

        // Act
        $value = User::query()->sum('age');

        // Assert
        self::assertSame(4500, $value);
        self::assertEquals(['sum' => ['field' => 'age']], $this->client->lastParams('search')['body']['aggs']['metric']);
    }

    public function test_min_and_max_return_the_aggregated_values(): void
    {
        // Arrange
        $this->client->queue('search', [
            'hits' => ['total' => ['value' => 2], 'hits' => []],
            'aggregations' => ['metric' => ['value' => 1.5]],
        ]);
        $this->client->queue('search', [
            'hits' => ['total' => ['value' => 2], 'hits' => []],
            'aggregations' => ['metric' => ['value' => 9.5]],
        ]);

        // Act
        $min = User::query()->min('price');
        $max = User::query()->max('price');

        // Assert
        self::assertSame(1.5, $min);
        self::assertSame(9.5, $max);
        self::assertEquals(['min' => ['field' => 'price']], $this->client->callsFor('search')[0]['params']['body']['aggs']['metric']);
        self::assertEquals(['max' => ['field' => 'price']], $this->client->callsFor('search')[1]['params']['body']['aggs']['metric']);
    }

    public function test_metric_shortcuts_return_null_when_no_document_matches(): void
    {
        // Arrange
        $this->client->setResponse('search', [
            'hits' => ['total' => ['value' => 0], 'hits' => []],
            'aggregations' => ['metric' => ['value' => null]],
        ]);

        // Act
        $value = User::query()->avg('age');

        // Assert
        self::assertNull($value);
    }

    /* ------------------------------------------------------------------
     |  deleteByQuery()
     | ------------------------------------------------------------------ */

    public function test_delete_by_query_sends_the_query_and_returns_the_deleted_count(): void
    {
        // Arrange
        $this->client->setResponse('deleteByQuery', ['took' => 25, 'total' => 5, 'deleted' => 5]);

        // Act
        $deleted = User::query()->where('status', 'banned')->deleteByQuery(['refresh' => true]);

        // Assert
        self::assertSame(5, $deleted);

        $params = $this->client->lastParams('deleteByQuery');
        self::assertSame('users', $params['index']);
        self::assertTrue($params['refresh']);
        self::assertEquals(
            ['bool' => ['must' => [['term' => ['status' => 'banned']]]]],
            $params['body']['query'],
        );
    }

    public function test_delete_by_query_returns_zero_by_default(): void
    {
        // Act
        $deleted = User::query()->deleteByQuery();

        // Assert
        self::assertSame(0, $deleted);
        self::assertSame('users', $this->client->lastParams('deleteByQuery')['index']);
    }

    /* ------------------------------------------------------------------
     |  firstOrNew / firstOrCreate / updateOrCreate
     | ------------------------------------------------------------------ */

    public function test_first_or_new_returns_the_existing_model_without_saving(): void
    {
        // Arrange
        $this->client->setResponse('search', [
            'hits' => ['total' => ['value' => 1], 'hits' => [['_id' => '1', '_source' => ['name' => 'Tom', 'email' => 'tom@example.com']]]],
        ]);

        // Act
        $model = User::query()->firstOrNew(['email' => 'tom@example.com'], ['name' => 'Tommy']);

        // Assert
        self::assertSame('Tom', $model->name);
        self::assertTrue($model->exists());
        self::assertSame(0, $this->client->callCount('index'));
        self::assertSame(0, $this->client->callCount('update'));
    }

    public function test_first_or_new_returns_an_unsaved_instance_when_nothing_matches(): void
    {
        // Act
        $model = User::query()->firstOrNew(['email' => 'new@example.com'], ['name' => 'New']);

        // Assert
        self::assertSame('New', $model->name);
        self::assertSame('new@example.com', $model->email);
        self::assertFalse($model->exists());
        self::assertSame(0, $this->client->callCount('index'));
    }

    public function test_first_or_create_returns_the_existing_model_without_an_index_request(): void
    {
        // Arrange
        $this->client->setResponse('search', [
            'hits' => ['total' => ['value' => 1], 'hits' => [['_id' => '1', '_source' => ['name' => 'Tom']]]],
        ]);

        // Act
        $model = User::query()->firstOrCreate(['email' => 'tom@example.com'], ['name' => 'Tommy']);

        // Assert
        self::assertSame('Tom', $model->name);
        self::assertSame(0, $this->client->callCount('index'));
    }

    public function test_first_or_create_creates_the_document_when_nothing_matches(): void
    {
        // Arrange
        $this->client->setResponse('index', ['_id' => '42']);

        // Act
        $model = User::query()->firstOrCreate(['email' => 'new@example.com'], ['name' => 'New']);

        // Assert
        self::assertSame('New', $model->name);
        self::assertSame('42', $model->getKey());
        self::assertTrue($model->exists());

        $params = $this->client->lastParams('index');
        self::assertSame('users', $params['index']);
        self::assertSame('new@example.com', $params['body']['email']);
        self::assertSame('New', $params['body']['name']);
    }

    public function test_update_or_create_creates_the_document_when_nothing_matches(): void
    {
        // Arrange
        $this->client->setResponse('index', ['_id' => '7']);

        // Act
        $model = User::query()->updateOrCreate(['email' => 'new@example.com'], ['name' => 'New', 'age' => 30]);

        // Assert
        self::assertSame('New', $model->name);
        self::assertSame('7', $model->getKey());
        self::assertSame('index', $this->client->lastOperation());
    }

    public function test_update_or_create_updates_the_existing_document(): void
    {
        // Arrange
        $this->client->setResponse('search', [
            'hits' => ['total' => ['value' => 1], 'hits' => [['_id' => '1', '_source' => ['name' => 'Tom', 'email' => 'tom@example.com']]]],
        ]);

        // Act
        $model = User::query()->updateOrCreate(['email' => 'tom@example.com'], ['age' => 31]);

        // Assert
        self::assertSame(31, $model->age);
        self::assertSame('update', $this->client->lastOperation());

        $params = $this->client->lastParams('update');
        self::assertSame('1', $params['id']);
        self::assertSame(31, $params['body']['doc']['age']);
    }
}
