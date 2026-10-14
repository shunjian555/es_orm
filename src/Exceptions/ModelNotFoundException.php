<?php

declare(strict_types=1);

namespace EsOrm\Exceptions;

use Throwable;

/**
 * Thrown when a model cannot be found for the given identifier, mirroring the
 * behaviour of Eloquent's ModelNotFoundException.
 *
 * @phpstan-consistent-constructor
 */
class ModelNotFoundException extends EsOrmException
{
    /**
     * @param string $message The human readable error message.
     * @param string $model The fully qualified model class name.
     * @param mixed $id The identifier that could not be resolved.
     * @param Throwable|null $previous The throwable that caused this exception, if any.
     */
    public function __construct(
        string $message = 'No results found.',
        private readonly string $model = '',
        private readonly mixed $id = null,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }

    /**
     * Create a new exception for a specific model and identifier.
     *
     * @param string $model The fully qualified model class name.
     * @param mixed $id The identifier that could not be resolved.
     * @param Throwable|null $previous The throwable that caused this exception, if any.
     *
     * @return static
     */
    public static function forModel(string $model, mixed $id, ?Throwable $previous = null): static
    {
        return new static(sprintf('No results found for model [%s] with id [%s].', $model, is_scalar($id) ? $id : json_encode($id)), $model, $id, $previous);
    }

    /**
     * Get the model class name associated with this exception.
     *
     * @return string
     */
    public function getModel(): string
    {
        return $this->model;
    }

    /**
     * Get the identifier that could not be resolved.
     *
     * @return mixed
     */
    public function getId(): mixed
    {
        return $this->id;
    }
}
