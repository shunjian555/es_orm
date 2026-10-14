<?php

declare(strict_types=1);

namespace EsOrm\Tests\Fixtures;

use EsOrm\Model\Model;
use EsOrm\Relation\BelongsTo;
use EsOrm\Relation\HasMany;

/**
 * A model with relations and a mapping declaration.
 */
final class Article extends Model
{
    protected string $index = 'articles';

    /**
     * @var list<string>
     */
    protected array $fillable = [
        'id',
        'title',
        'body',
        'author_id',
        'status',
        'created_at',
    ];

    /**
     * @var array<string, string>
     */
    protected array $casts = [
        'created_at' => 'datetime',
    ];

    public function author(): BelongsTo
    {
        return $this->belongsTo(Author::class, 'author_id');
    }

    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class, 'article_id');
    }

    /**
     * @return array<string, mixed>
     */
    public function mapping(): array
    {
        return [
            'properties' => [
                'title' => [
                    'type' => 'text',
                    'fields' => ['keyword' => ['type' => 'keyword']],
                ],
                'status' => ['type' => 'keyword'],
                'created_at' => ['type' => 'date'],
            ],
        ];
    }
}
