<?php

declare(strict_types=1);

namespace EsOrm\Tests\Fixtures;

use EsOrm\Model\Model;
use EsOrm\Query\Builder;

/**
 * A plain model with casts and a local scope, used across the unit tests.
 */
final class User extends Model
{
    protected string $index = 'users';

    protected string $primaryKey = 'id';

    /**
     * @var list<string>
     */
    protected array $fillable = [
        'id',
        'name',
        'email',
        'age',
        'status',
        'vip',
        'active',
        'price',
        'metadata',
        'options',
        'created_at',
        'birthday',
        'profile',
    ];

    /**
     * @var array<string, string>
     */
    protected array $casts = [
        'age' => 'integer',
        'price' => 'float',
        'active' => 'boolean',
        'metadata' => 'array',
        'options' => 'json',
        'created_at' => 'datetime',
        'birthday' => 'date',
        'profile' => 'object',
    ];

    /**
     * @var array<string, mixed>
     */
    protected array $hidden = [];

    public function scopeActive(Builder $query): Builder
    {
        return $query->term('status', 'active');
    }

    public function scopeOlderThan(Builder $query, int $age): Builder
    {
        return $query->where('age', '>', $age);
    }
}
