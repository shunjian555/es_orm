<?php

declare(strict_types=1);

namespace EsOrm\Exceptions;

use Throwable;

/**
 * Thrown when attribute or document validation fails before a request is sent
 * to the cluster. It carries the full set of validation errors so callers can
 * inspect them programmatically.
 */
class ValidationException extends EsOrmException
{
    /**
     * @param string $message The human readable error message.
     * @param array<string, mixed> $errors The validation errors keyed by field.
     * @param Throwable|null $previous The throwable that caused this exception, if any.
     */
    public function __construct(
        string $message = 'The given data was invalid.',
        private readonly array $errors = [],
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }

    /**
     * Get the validation errors carried by this exception.
     *
     * @return array<string, mixed>
     */
    public function errors(): array
    {
        return $this->errors;
    }
}
