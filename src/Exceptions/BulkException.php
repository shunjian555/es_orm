<?php

declare(strict_types=1);

namespace EsOrm\Exceptions;

use Throwable;

/**
 * Thrown when a bulk operation reports one or more failed items so callers can
 * inspect the individual failures.
 *
 * @phpstan-consistent-constructor
 */
class BulkException extends EsOrmException
{
    /**
     * @param string $message The human readable error message.
     * @param array<int, mixed> $failures The individual bulk failures.
     * @param Throwable|null $previous The throwable that caused this exception, if any.
     */
    public function __construct(
        string $message = 'Bulk operation failed.',
        private readonly array $failures = [],
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }

    /**
     * Create a new exception from the reported bulk failures.
     *
     * @param array<int, mixed> $failures The individual bulk failures.
     * @param Throwable|null $previous The throwable that caused this exception, if any.
     *
     * @return static
     */
    public static function fromFailures(array $failures, ?Throwable $previous = null): static
    {
        return new static(sprintf('%d bulk operation(s) failed.', count($failures)), $failures, $previous);
    }

    /**
     * Get the individual bulk failures carried by this exception.
     *
     * @return array<int, mixed>
     */
    public function failures(): array
    {
        return $this->failures;
    }
}
