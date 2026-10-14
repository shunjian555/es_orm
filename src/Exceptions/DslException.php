<?php

declare(strict_types=1);

namespace EsOrm\Exceptions;

/**
 * Thrown when an invalid DSL fragment is constructed, for example when a query
 * or aggregation object cannot be represented as a valid Elasticsearch array.
 */
class DslException extends EsOrmException
{
}
