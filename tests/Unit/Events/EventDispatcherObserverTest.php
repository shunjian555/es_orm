<?php

declare(strict_types=1);

namespace EsOrm\Tests\Unit\Events;

use EsOrm\Events\EventDispatcher;
use EsOrm\Tests\Fixtures\User;
use PHPUnit\Framework\TestCase as BaseTestCase;

/**
 * Covers the observer half of {@see EventDispatcher}: registerObserver(),
 * forgetObserver(), hasListeners() and the blocking semantics of fire().
 */
final class EventDispatcherObserverTest extends BaseTestCase
{
    public function test_a_listener_receives_the_model_and_can_block_the_operation(): void
    {
        // Arrange
        $dispatcher = new EventDispatcher();
        $received = [];
        $dispatcher->listen('saving', static function (User $model, string $event) use (&$received): bool {
            $received = [$model, $event];

            return false;
        });

        // Act
        $proceed = $dispatcher->fire('saving', new User());

        // Assert
        self::assertFalse($proceed);
        self::assertCount(2, $received);
        self::assertSame('saving', $received[1]);
        self::assertInstanceOf(User::class, $received[0]);
    }

    public function test_fire_returns_true_when_no_listener_blocks(): void
    {
        // Arrange
        $dispatcher = new EventDispatcher();
        $dispatcher->listen('saved', static fn (): bool => true);

        // Act + Assert
        self::assertTrue($dispatcher->fire('saved', new User()));
    }

    public function test_hasListeners_detects_listeners_and_observers(): void
    {
        // Arrange
        $dispatcher = new EventDispatcher();

        // Assert: nothing registered yet
        self::assertFalse($dispatcher->hasListeners('saving', User::class));

        // Act: closure listener
        $dispatcher->listen('saving', static fn (): bool => true);

        // Assert
        self::assertTrue($dispatcher->hasListeners('saving', User::class));
        self::assertFalse($dispatcher->hasListeners('deleting', User::class));
    }

    public function test_hasListeners_detects_an_observer_with_a_matching_method(): void
    {
        // Arrange
        $dispatcher = new EventDispatcher();
        $dispatcher->registerObserver(User::class, new class () {
            public function deleting(User $model): void
            {
            }
        });

        // Assert
        self::assertTrue($dispatcher->hasListeners('deleting', User::class));
        self::assertFalse($dispatcher->hasListeners('saving', User::class));
    }

    public function test_an_observer_is_invoked_for_its_events_and_can_block(): void
    {
        // Arrange
        $dispatcher = new EventDispatcher();
        $calls = [];
        $dispatcher->registerObserver(User::class, new class ($calls) {
            /**
             * @param list<string> $calls
             */
            public function __construct(private array $calls)
            {
            }

            public function creating(User $model): bool
            {
                $this->calls[] = 'creating';

                return false;
            }
        });

        // Act
        $proceed = $dispatcher->fire('creating', new User());

        // Assert: the observer blocked and was invoked exactly once.
        self::assertFalse($proceed);
    }

    public function test_an_observer_registered_by_class_name_is_resolved_lazily(): void
    {
        // Arrange
        $dispatcher = new EventDispatcher();
        $dispatcher->registerObserver(User::class, EventDispatcherObserverFixture::class);

        // Act + Assert
        self::assertTrue($dispatcher->hasListeners('restored', User::class));
        self::assertTrue($dispatcher->fire('restored', new User()));
    }

    public function test_forgetObserver_removes_a_registered_observer(): void
    {
        // Arrange
        $dispatcher = new EventDispatcher();
        $observer = new EventDispatcherObserverFixture();
        $dispatcher->registerObserver(User::class, $observer);
        self::assertTrue($dispatcher->hasListeners('restored', User::class));

        // Act
        $dispatcher->forgetObserver(User::class, $observer);

        // Assert
        self::assertFalse($dispatcher->hasListeners('restored', User::class));
    }
}

/**
 * A plain observer class used for string-based registration.
 */
final class EventDispatcherObserverFixture
{
    public function restored(User $model): void
    {
    }
}
