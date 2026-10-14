<?php

declare(strict_types=1);

namespace EsOrm\Tests\Fixtures;

use EsOrm\Model\Attribute;
use EsOrm\Model\Model;

/**
 * A model exercising `Attribute::make()` accessors and mutators.
 */
final class AccessorModel extends Model
{
    protected string $index = 'accessor_models';

    /**
     * @var list<string>
     */
    protected array $fillable = ['id', 'name', 'prefix', 'nickname'];

    /**
     * Uppercases on read, lowercases on write.
     */
    protected function name(): Attribute
    {
        return Attribute::make(
            get: static fn (mixed $value): string => strtoupper((string) $value),
            set: static fn (mixed $value): string => strtolower((string) $value),
        );
    }

    /**
     * A virtual attribute built from other attributes, proving the getter
     * receives the whole attribute array.
     */
    protected function label(): Attribute
    {
        return Attribute::make(
            get: static fn (mixed $value, array $attributes): string => ($attributes['prefix'] ?? '') . ':' . ($attributes['name'] ?? ''),
        );
    }

    /**
     * Not an `Attribute` return type, so it must be ignored by the mutator cache.
     */
    protected function nickname(): string
    {
        return 'ignored';
    }
}
