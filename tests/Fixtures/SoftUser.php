<?php

declare(strict_types=1);

namespace EsOrm\Tests\Fixtures;

use EsOrm\Model\Model;
use EsOrm\Model\SoftDeletes;

/**
 * A soft-deleting model.
 */
final class SoftUser extends Model
{
    use SoftDeletes;

    protected string $index = 'soft_users';

    /**
     * @var list<string>
     */
    protected array $fillable = ['id', 'name', 'status', 'deleted_at'];

    /**
     * @var array<string, string>
     */
    protected array $casts = [
        'deleted_at' => 'datetime',
    ];
}
