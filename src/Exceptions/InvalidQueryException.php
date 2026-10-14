<?php

declare(strict_types=1);

namespace EsOrm\Exceptions;

/**
 * Thrown when an invalid query is built locally, before it is sent to the
 * cluster, for example when a required clause argument is missing.
 */
class InvalidQueryException extends EsOrmException
{
}
