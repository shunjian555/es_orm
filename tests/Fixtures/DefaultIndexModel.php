<?php

declare(strict_types=1);

namespace EsOrm\Tests\Fixtures;

use EsOrm\Model\Model;

/**
 * A model that declares no explicit index, so `getIndex()` must derive it from
 * the class name (`default_index_models`).
 */
final class DefaultIndexModel extends Model
{
}
