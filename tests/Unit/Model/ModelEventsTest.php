<?php

declare(strict_types=1);

namespace EsOrm\Tests\Unit\Model;

use EsOrm\Model\Model;
use EsOrm\Tests\Fixtures\User;
use EsOrm\Tests\TestCase;

final class ModelEventsTest extends TestCase
{
    /**
     * Register one recording listener per event.
     *
     * @param list<string> $events
     * @param list<string> $log
     */
    private function listen(array $events, array &$log): void
    {
        $dispatcher = Model::eventDispatcher();

        foreach ($events as $event) {
            $dispatcher->listen($event, static function (Model $model) use (&$log, $event): void {
                $log[] = $event;
            });
        }
    }

    public function test_it_fires_events_in_order_when_creating(): void
    {
        // Arrange
        $log = [];
        $this->listen(['saving', 'creating', 'created', 'saved'], $log);
        $this->client->setResponse('index', ['_id' => '1']);

        // Act
        User::create(['name' => 'Tom']);

        // Assert
        self::assertSame(['saving', 'creating', 'created', 'saved'], $log);
    }

    public function test_it_fires_events_in_order_when_updating(): void
    {
        // Arrange
        $log = [];
        $this->listen(['saving', 'updating', 'updated', 'saved'], $log);
        $model = (new User())->newInstance(['id' => '1', 'name' => 'Tom'], true);

        // Act
        $model->save();

        // Assert
        self::assertSame(['saving', 'updating', 'updated', 'saved'], $log);
    }

    public function test_it_fires_events_in_order_when_deleting(): void
    {
        // Arrange
        $log = [];
        $this->listen(['deleting', 'deleted'], $log);
        $model = (new User())->newInstance(['id' => '1'], true);

        // Act
        $model->delete();

        // Assert
        self::assertSame(['deleting', 'deleted'], $log);
    }

    public function test_a_listener_returning_false_aborts_creation(): void
    {
        // Arrange
        Model::eventDispatcher()->listen('creating', static fn (): bool => false);

        // Act
        User::create(['name' => 'Tom']);

        // Assert
        self::assertSame(0, $this->client->callCount('index'));
    }

    public function test_a_listener_returning_false_aborts_saving(): void
    {
        // Arrange
        Model::eventDispatcher()->listen('saving', static fn (): bool => false);
        $model = new User();
        $model->fill(['name' => 'Tom']);

        // Act
        $saved = $model->save();

        // Assert
        self::assertFalse($saved);
        self::assertSame(0, $this->client->callCount('index'));
    }

    public function test_a_listener_returning_false_aborts_a_update(): void
    {
        // Arrange
        Model::eventDispatcher()->listen('updating', static fn (): bool => false);
        $model = (new User())->newInstance(['id' => '1', 'name' => 'Tom'], true);

        // Act
        $saved = $model->save();

        // Assert
        self::assertFalse($saved);
        self::assertSame(0, $this->client->callCount('update'));
    }

    public function test_a_listener_returning_false_aborts_deletion(): void
    {
        // Arrange
        Model::eventDispatcher()->listen('deleting', static fn (): bool => false);
        $model = (new User())->newInstance(['id' => '1'], true);

        // Act
        $deleted = $model->delete();

        // Assert
        self::assertFalse($deleted);
        self::assertSame(0, $this->client->callCount('delete'));
    }

    public function test_it_does_not_raise_events_when_no_listener_is_registered(): void
    {
        // Arrange
        $this->client->setResponse('index', ['_id' => '1']);
        $model = new User();
        $model->fill(['name' => 'Tom']);

        // Act
        $saved = $model->save();

        // Assert
        self::assertTrue($saved);
        self::assertSame(1, $this->client->callCount('index'));
    }
}
