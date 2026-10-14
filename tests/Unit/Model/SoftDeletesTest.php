<?php

declare(strict_types=1);

namespace EsOrm\Tests\Unit\Model;

use EsOrm\Model\Model;
use EsOrm\Tests\Fixtures\SoftUser;
use EsOrm\Tests\Fixtures\User;
use EsOrm\Tests\TestCase;

final class SoftDeletesTest extends TestCase
{
    public function test_it_detects_soft_delete_support(): void
    {
        // Act & Assert
        self::assertTrue((new SoftUser())->usesSoftDeletes());
        self::assertFalse((new User())->usesSoftDeletes());
        self::assertSame('deleted_at', (new SoftUser())->getDeletedAtColumn());
    }

    public function test_it_reports_trashed_state_from_the_deleted_at_column(): void
    {
        // Arrange
        $fresh = (new SoftUser())->newInstance(['id' => '1', 'name' => 'Tom'], true);
        $trashed = (new SoftUser())->newInstance(['id' => '2', 'deleted_at' => '2024-01-01T00:00:00+00:00'], true);

        // Act & Assert
        self::assertFalse($fresh->trashed());
        self::assertTrue($trashed->trashed());
    }

    public function test_delete_soft_deletes_by_updating_the_deleted_at_column(): void
    {
        // Arrange
        $this->client->setResponse('update', ['result' => 'updated']);
        $model = (new SoftUser())->newInstance(['id' => '7', 'name' => 'Tom'], true);

        // Act
        $deleted = $model->delete();

        // Assert
        self::assertTrue($deleted);
        self::assertTrue($model->trashed());
        $params = $this->client->lastParams('update');
        self::assertSame('soft_users', $params['index']);
        self::assertSame('7', $params['id']);
        self::assertArrayHasKey('doc', $params['body']);
        self::assertArrayHasKey('deleted_at', $params['body']['doc']);
        self::assertSame(['id', 'name', 'deleted_at'], array_keys($params['body']['doc']));
    }

    public function test_soft_deleting_does_not_issue_a_delete_request(): void
    {
        // Arrange
        $model = (new SoftUser())->newInstance(['id' => '7', 'name' => 'Tom'], true);

        // Act
        $model->delete();

        // Assert
        self::assertSame(0, $this->client->callCount('delete'));
        self::assertSame(1, $this->client->callCount('update'));
    }

    public function test_it_fires_updating_and_deleted_events_when_soft_deleting(): void
    {
        // Arrange
        $log = [];
        $dispatcher = Model::eventDispatcher();
        foreach (['deleting', 'updating', 'updated', 'deleted'] as $event) {
            $dispatcher->listen($event, static function (Model $model) use (&$log, $event): void {
                $log[] = $event;
            });
        }
        $model = (new SoftUser())->newInstance(['id' => '7'], true);

        // Act
        $model->delete();

        // Assert
        self::assertSame(['deleting', 'updating', 'updated', 'deleted'], $log);
    }

    public function test_restore_clears_the_deleted_at_column_with_an_update(): void
    {
        // Arrange
        $this->client->setResponse('update', ['result' => 'updated']);
        $model = (new SoftUser())->newInstance(['id' => '7', 'name' => 'Tom', 'deleted_at' => '2024-01-01T00:00:00+00:00'], true);

        // Act
        $restored = $model->restore();

        // Assert
        self::assertTrue($restored);
        self::assertFalse($model->trashed());
        $params = $this->client->lastParams('update');
        self::assertSame('soft_users', $params['index']);
        self::assertSame('7', $params['id']);
        self::assertNull($params['body']['doc']['deleted_at']);
    }

    public function test_restore_returns_false_when_the_model_is_not_trashed(): void
    {
        // Arrange
        $model = (new SoftUser())->newInstance(['id' => '7'], true);

        // Act
        $restored = $model->restore();

        // Assert
        self::assertFalse($restored);
        self::assertSame(0, $this->client->callCount('update'));
    }

    public function test_force_delete_permanently_removes_the_document(): void
    {
        // Arrange
        $model = (new SoftUser())->newInstance(['id' => '7', 'name' => 'Tom'], true);

        // Act
        $deleted = $model->forceDelete();

        // Assert
        self::assertTrue($deleted);
        self::assertFalse($model->exists());
        self::assertSame(['index' => 'soft_users', 'id' => '7'], $this->client->lastParams('delete'));
        self::assertSame(0, $this->client->callCount('update'));
    }

    public function test_the_default_query_excludes_trashed_documents(): void
    {
        // Act
        $dsl = SoftUser::query()->where('name', 'Tom')->toDsl();

        // Assert
        self::assertSame(
            [['bool' => ['must_not' => [['exists' => ['field' => 'deleted_at']]]]]],
            $dsl['query']['bool']['filter'],
        );
    }

    public function test_with_trashed_query_includes_trashed_documents(): void
    {
        // Act
        $dsl = SoftUser::query()->withTrashed()->where('name', 'Tom')->toDsl();

        // Assert
        self::assertSame(['bool' => ['must' => [['term' => ['name' => 'Tom']]]]], $dsl['query']);
        self::assertArrayNotHasKey('filter', $dsl['query']['bool']);
    }

    public function test_only_trashed_query_selects_only_trashed_documents(): void
    {
        // Act
        $dsl = SoftUser::query()->onlyTrashed()->toDsl();

        // Assert
        self::assertSame(
            ['bool' => ['filter' => [['exists' => ['field' => 'deleted_at']]]]],
            $dsl['query'],
        );
    }

    public function test_find_applies_the_soft_delete_filter(): void
    {
        // Arrange
        $this->client->setResponse('search', ['hits' => ['total' => ['value' => 0], 'hits' => []]]);

        // Act
        SoftUser::find('7');

        // Assert
        $params = $this->client->lastParams('search');
        self::assertSame(
            [['bool' => ['must_not' => [['exists' => ['field' => 'deleted_at']]]]]],
            $params['body']['query']['bool']['filter'],
        );
    }
}
