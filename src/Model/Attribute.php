<?php

declare(strict_types=1);

namespace EsOrm\Model;

use Closure;

/**
 * An accessor/mutator pair, in the spirit of `Illuminate\Database\Eloquent\Casts\Attribute`.
 *
 * Usage inside a model:
 *
 * ```php
 * protected function name(): Attribute
 * {
 *     return Attribute::make(
 *         get: fn (mixed $value): string => ucfirst((string) $value),
 *         set: fn (mixed $value): string => strtolower((string) $value),
 *     );
 * }
 * ```
 *
 * Both closures receive the raw value; the getter additionally receives the
 * full attribute array so cross-field accessors are possible.
 */
final class Attribute
{
    /**
     * @param Closure(mixed, array<string, mixed>): mixed|null $get
     * @param Closure(mixed, array<string, mixed>): mixed|null $set
     */
    public function __construct(
        public readonly ?Closure $get = null,
        public readonly ?Closure $set = null,
    ) {
    }

    /**
     * @param callable(mixed, array<string, mixed>): mixed|null $get
     * @param callable(mixed, array<string, mixed>): mixed|null $set
     */
    public static function make(?callable $get = null, ?callable $set = null): self
    {
        return new self(
            get: $get === null ? null : Closure::fromCallable($get),
            set: $set === null ? null : Closure::fromCallable($set),
        );
    }

    public function hasGetter(): bool
    {
        return $this->get instanceof Closure;
    }

    public function hasSetter(): bool
    {
        return $this->set instanceof Closure;
    }

    /**
     * Transform a value on read.
     *
     * @param array<string, mixed> $attributes
     */
    public function get(mixed $value, array $attributes = []): mixed
    {
        if (!$this->get instanceof Closure) {
            return $value;
        }

        return ($this->get)($value, $attributes);
    }

    /**
     * Transform a value on write.
     *
     * @param array<string, mixed> $attributes
     */
    public function set(mixed $value, array $attributes = []): mixed
    {
        if (!$this->set instanceof Closure) {
            return $value;
        }

        return ($this->set)($value, $attributes);
    }
}
