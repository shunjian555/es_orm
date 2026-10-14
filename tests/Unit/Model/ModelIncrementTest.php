<?php

declare(strict_types=1);

namespace EsOrm\Tests\Unit\Model;

use EsOrm\Exceptions\InvalidQueryException;
use EsOrm\Model\Model;
use EsOrm\Tests\Fixtures\User;
use EsOrm\Tests\TestCase;

/**
 * Covers the atomic increment()/decrement() model API.
 */
final class ModelIncrementTest extends TestCase
{
    public function test_increment_sends_a_painless_script_update(): void
    {
        // Arrange
        $model = (new User())->newInstance(['id' => '1', 'age' => 30], true);

        // Act
        $result = $model->increment('age', 5);

        // Assert
        self::assertTrue($result);
        self::assertSame(35, $model->age);

        $params = $this->client->lastParams('update');
        self::assertSame('users', $params['index']);
        self::assertSame('1', $params['id']);
        self::assertSame(3, $params['body']['retry_on_conflict']);
        self::assertSame(
            'ctx._source[params.column] = (ctx._source[params.column] == null ? 0 : ctx._source[params.column]) + params.amount',
            $params['body']['script']['source'],
        );
        self::assertSame(['column' => 'age', 'amount' => 5], $params['body']['script']['params']);
    }

    public function test_decrement_sends_a_negative_amount(): void
    {
        // Arrange
        $model = (new User())->newInstance(['id' => '1', 'age' => 30], true);

        // Act
        $model->decrement('age', 5);

        // Assert
        self::assertSame(25, $model->age);
        self::assertSame(['column' => 'age', 'amount' => -5], $this->client->lastParams('update')['body']['script']['params']);
    }

    public function test_increment_defaults_to_one_step(): void
    {
        // Arrange
        $model = (new User())->newInstance(['id' => '1'], true);

        // Act
        $model->increment('age');

        // Assert
        self::assertSame(1, $model->age);
        self::assertSame(['column' => 'age', 'amount' => 1], $this->client->lastParams('update')['body']['script']['params']);
    }

    public function test_increment_writes_extra_attributes_in_the_same_request(): void
    {
        // Arrange
        $model = (new User())->newInstance(['id' => '1', 'age' => 30], true);

        // Act
        $model->increment('age', 1, ['status' => 'active']);

        // Assert
        self::assertSame(31, $model->age);
        self::assertSame('active', $model->status);

        $script = $this->client->lastParams('update')['body']['script'];
        self::assertStringContainsString('params.extra.entrySet()', $script['source']);
        self::assertSame(['column' => 'age', 'amount' => 1, 'extra' => ['status' => 'active']], $script['params']);
    }

    public function test_increment_treats_a_missing_field_as_zero(): void
    {
        // Arrange
        $model = (new User())->newInstance(['id' => '1'], true);

        // Act
        $model->increment('age', 10);

        // Assert
        self::assertSame(10, $model->age);
    }

    public function test_increment_fires_the_updating_and_updated_events(): void
    {
        // Arrange
        $log = [];
        $dispatcher = Model::eventDispatcher();

        foreach (['updating', 'updated'] as $event) {
            $dispatcher->listen($event, static function (Model $model) use (&$log, $event): void {
                $log[] = $event;
            });
        }

        $model = (new User())->newInstance(['id' => '1', 'age' => 30], true);

        // Act
        $model->increment('age');

        // Assert
        self::assertSame(['updating', 'updated'], $log);
    }

    public function test_a_listener_returning_false_aborts_the_increment(): void
    {
        // Arrange
        Model::eventDispatcher()->listen('updating', static fn (): bool => false);
        $model = (new User())->newInstance(['id' => '1', 'age' => 30], true);

        // Act
        $result = $model->increment('age');

        // Assert
        self::assertFalse($result);
        self::assertSame(30, $model->age);
        self::assertSame(0, $this->client->callCount('update'));
    }

    public function test_increment_throws_for_a_model_that_does_not_exist(): void
    {
        $this->expectException(InvalidQueryException::class);

        (new User())->increment('age');
    }
}
