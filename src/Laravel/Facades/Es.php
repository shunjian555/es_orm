<?php

declare(strict_types=1);

namespace EsOrm\Laravel\Facades;

use EsOrm\Contracts\ConnectionInterface;
use EsOrm\Contracts\ConnectionManagerInterface;
use EsOrm\Index\AliasManager;
use EsOrm\Index\IndexManager;
use EsOrm\Index\ReindexManager;
use EsOrm\Laravel\EsManager;
use EsOrm\Migration\Migrator;
use Illuminate\Support\Facades\Facade;

/**
 * The `Es` facade.
 *
 * @method static ConnectionManagerInterface connections()
 * @method static ConnectionInterface connection(?string $name = null)
 * @method static IndexManager index(?string $name = null)
 * @method static AliasManager alias(?string $name = null)
 * @method static ReindexManager reindex(?string $name = null)
 * @method static Migrator migrator()
 * @method static array<string, mixed> search(array<string, mixed> $params, ?string $name = null)
 *
 * @see EsManager
 */
final class Es extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return EsManager::class;
    }
}
