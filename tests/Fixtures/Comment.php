<?php

declare(strict_types=1);

namespace EsOrm\Tests\Fixtures;

use EsOrm\Model\Model;
use EsOrm\Relation\BelongsTo;

final class Comment extends Model
{
    protected string $index = 'comments';

    /**
     * @var list<string>
     */
    protected array $fillable = ['id', 'article_id', 'body'];

    public function article(): BelongsTo
    {
        return $this->belongsTo(Article::class, 'article_id');
    }
}
