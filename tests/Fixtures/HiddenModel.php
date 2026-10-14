<?php

declare(strict_types=1);

namespace EsOrm\Tests\Fixtures;

use EsOrm\Model\Model;

/**
 * A model hiding one attribute from the array/JSON output.
 */
final class HiddenModel extends Model
{
    protected string $index = 'hidden_models';

    /**
     * @var list<string>
     */
    protected array $hidden = ['secret'];
}
