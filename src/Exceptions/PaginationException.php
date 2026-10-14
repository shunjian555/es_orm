<?php

declare(strict_types=1);

namespace EsOrm\Exceptions;

/**
 * Thrown when pagination cannot be performed, for example when combining
 * offset based paging with deep pagination strategies such as search after.
 */
class PaginationException extends EsOrmException
{
}
