<?php

declare(strict_types=1);

namespace EsOrm\Exceptions;

/**
 * Thrown when a reindex operation fails, for example when the source index is
 * missing or the copy cannot be completed successfully.
 */
class ReindexException extends EsOrmException
{
}
