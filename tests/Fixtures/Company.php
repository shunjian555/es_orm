<?php

declare(strict_types=1);

namespace EsOrm\Tests\Fixtures;

use EsOrm\Model\Model;
use EsOrm\Relation\HasMany;

final class Company extends Model
{
    protected string $index = 'companies';

    /**
     * @var list<string>
     */
    protected array $fillable = ['id', 'name'];

    public function posts(): HasMany
    {
        return $this->hasMany(Post::class, 'company_id');
    }
}
