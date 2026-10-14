<?php

declare(strict_types=1);

namespace EsOrm\Exceptions;

use RuntimeException;
use Throwable;

/**
 * Base class for every exception thrown by the Elasticsearch ORM.
 *
 * Every package specific failure extends this type so consumers can catch a
 * single exception class to handle anything raised by the ORM. It keeps a
 * small, optional context payload that subclasses may populate to describe the
 * failing operation without changing the underlying RuntimeException contract.
 */
class EsOrmException extends RuntimeException
{
    /**
     * Contextual data describing the failure.
     *
     * @var array<string, mixed>
     */
    protected array $context = [];

    /**
     * Create an exception from an existing throwable, forwarding its code.
     *
     * @param string $message The human readable error message.
     * @param Throwable|null $previous The throwable that caused this exception, if any.
     *
     * @return self
     */
    public static function fromPrevious(string $message, ?Throwable $previous = null): self
    {
        return new self($message, (int) $previous?->getCode(), $previous);
    }

    /**
     * Get the contextual data attached to this exception.
     *
     * @return array<string, mixed>
     */
    public function getContext(): array
    {
        return $this->context;
    }
}
