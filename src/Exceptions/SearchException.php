<?php

declare(strict_types=1);

namespace EsOrm\Exceptions;

/**
 * Thrown when a search request fails to execute or the returned response
 * cannot be interpreted by the ORM.
 */
class SearchException extends EsOrmException
{
}
