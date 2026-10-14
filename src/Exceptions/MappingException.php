<?php

declare(strict_types=1);

namespace EsOrm\Exceptions;

/**
 * Thrown when an index mapping cannot be built, validated or applied, such as
 * an invalid field type or a conflicting property definition.
 */
class MappingException extends EsOrmException
{
}
