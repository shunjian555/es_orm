<?php

declare(strict_types=1);

namespace EsOrm\Exceptions;

/**
 * Thrown when an index level operation fails, for example creating, deleting,
 * refreshing or managing the aliases of an index.
 */
class IndexException extends EsOrmException
{
}
