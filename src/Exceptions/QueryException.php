<?php

declare(strict_types=1);

namespace EsOrm\Exceptions;

/**
 * Thrown when the cluster accepts a request but rejects the query at runtime,
 * for example due to a malformed body or an unsupported operation.
 */
class QueryException extends EsOrmException
{
}
