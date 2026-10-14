<?php

declare(strict_types=1);

namespace EsOrm\Events;

use EsOrm\Contracts\EventDispatcherInterface;
use EsOrm\Model\Model;

/**
 * Default, framework agnostic model event dispatcher.
 *
 * It supports both closure listeners and class based observers, and needs no
 * Laravel container. A Laravel application can replace it with an adapter
 * around the framework dispatcher through `Model::setEventDispatcher()`.
 */
final class EventDispatcher implements EventDispatcherInterface
{
    /**
     * @var array<string, list<callable>>
     */
    private array $listeners = [];

    /**
     * @var array<class-string, list<object>>
     */
    private array $observers = [];

    public function listen(string $event, callable $listener): void
    {
        $this->listeners[$event][] = $listener;
    }

    public function registerObserver(string $modelClass, object|string $observer): void
    {
        if (is_string($observer)) {
            /** @var class-string $class */
            $class = $observer;
            $observer = new $class();
        }

        $this->observers[$modelClass][] = $observer;
    }

    public function forgetObserver(string $modelClass, object $observer): void
    {
        $observers = $this->observers[$modelClass] ?? [];

        $this->observers[$modelClass] = array_values(array_filter(
            $observers,
            static fn (object $registered): bool => $registered !== $observer,
        ));
    }

    public function fire(string $event, Model $model): bool
    {
        $proceed = true;

        foreach ($this->listeners[$event] ?? [] as $listener) {
            if ($listener($model, $event) === false) {
                $proceed = false;
            }
        }

        foreach ($this->observersFor($model::class) as $observer) {
            if (!method_exists($observer, $event)) {
                continue;
            }

            if ($observer->{$event}($model) === false) {
                $proceed = false;
            }
        }

        return $proceed;
    }

    public function hasListeners(string $event, string $modelClass): bool
    {
        if (($this->listeners[$event] ?? []) !== []) {
            return true;
        }

        foreach ($this->observersFor($modelClass) as $observer) {
            if (method_exists($observer, $event)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<callable>
     */
    public function listeners(string $event): array
    {
        return $this->listeners[$event] ?? [];
    }

    /**
     * Observers registered for a model class, including its parents.
     *
     * @return list<object>
     */
    public function observersFor(string $modelClass): array
    {
        $observers = $this->observers[$modelClass] ?? [];
        $parents = class_parents($modelClass);

        if (is_array($parents)) {
            foreach ($parents as $parent) {
                foreach ($this->observers[$parent] ?? [] as $observer) {
                    $observers[] = $observer;
                }
            }
        }

        return $observers;
    }
}
