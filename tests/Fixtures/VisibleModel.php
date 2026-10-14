<?php

declare(strict_types=1);

namespace EsOrm\Tests\Fixtures;

use EsOrm\Model\Model;

/**
 * A model exposing only a whitelisted set of attributes in the array/JSON
 * output.
 */
final class VisibleModel extends Model
{
    protected string $index = 'visible_models';

    /**
     * @var list<string>
     */
    protected array $visible = ['name'];
}
