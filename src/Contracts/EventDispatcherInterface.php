<?php

declare(strict_types=1);

namespace EsOrm\Contracts;

use EsOrm\Model\Model;

/**
 * Model event dispatcher contract.
 *
 * Events use camelCase names: `creating`, `created`, `saving`, `saved`,
 * `updating`, `updated`, `deleting`, `deleted`, `restoring`, `restored`,
 * `forceDeleting`, `forceDeleted`.
 */
interface EventDispatcherInterface
{
    /**
     * Register a listener for a model event.
     *
     * @param callable(Model, string): mixed $listener
     */
    public function listen(string $event, callable $listener): void;

    /**
     * Register an observer. Every public method matching an event name is
     * called with the model instance.
     */
    public function registerObserver(string $modelClass, object|string $observer): void;

    public function forgetObserver(string $modelClass, object $observer): void;

    /**
     * Fire a model event.
     *
     * @return bool false when a listener or observer returned `false`, which
     *              cancels the pending operation (`creating`, `updating`,
     *              `saving`, `deleting`, ...)
     */
    public function fire(string $event, Model $model): bool;

    public function hasListeners(string $event, string $modelClass): bool;
}
