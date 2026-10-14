<?php

declare(strict_types=1);

namespace EsOrm\Exceptions;

/**
 * Thrown when a query expected exactly one record (e.g. `sole()`) but several
 * documents matched, mirroring the behaviour of Eloquent's
 * MultipleRecordsFoundException.
 *
 * @phpstan-consistent-constructor
 */
class MultipleRecordsFoundException extends EsOrmException
{
    /**
     * Create an exception for the number of records that were found.
     *
     * @param int $count The number of records that matched the query.
     *
     * @return static
     */
    public static function forCount(int $count): static
    {
        return new static(sprintf('%d records were found, but a single record was expected.', $count));
    }
}
