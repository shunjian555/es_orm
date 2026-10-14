<?php

declare(strict_types=1);

namespace EsOrm\Exceptions;

/**
 * Thrown when the ORM cannot establish or maintain a connection to the
 * Elasticsearch cluster, for example on transport failures, timeouts or
 * authentication errors.
 */
class ConnectionException extends EsOrmException
{
}
