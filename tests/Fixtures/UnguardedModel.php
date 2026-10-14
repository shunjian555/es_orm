<?php

declare(strict_types=1);

namespace EsOrm\Tests\Fixtures;

use EsOrm\Model\Model;

/**
 * A fully unguarded model: every attribute is mass assignable except the ones
 * whose name starts with an underscore.
 */
final class UnguardedModel extends Model
{
    protected string $index = 'unguarded_models';

    /**
     * @var list<string>
     */
    protected array $guarded = [];
}
