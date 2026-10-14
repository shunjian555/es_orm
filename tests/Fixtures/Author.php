<?php

declare(strict_types=1);

namespace EsOrm\Tests\Fixtures;

use EsOrm\Model\Model;
use EsOrm\Relation\HasMany;

final class Author extends Model
{
    protected string $index = 'authors';

    /**
     * @var list<string>
     */
    protected array $fillable = ['id', 'name', 'company_id'];

    public function articles(): HasMany
    {
        return $this->hasMany(Article::class, 'author_id');
    }
}
