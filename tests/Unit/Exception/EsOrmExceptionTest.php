<?php

declare(strict_types=1);

namespace EsOrm\Tests\Unit\Exception;

use EsOrm\Exceptions\BulkException;
use EsOrm\Exceptions\ConnectionException;
use EsOrm\Exceptions\DslException;
use EsOrm\Exceptions\EsOrmException;
use EsOrm\Exceptions\IndexException;
use EsOrm\Exceptions\InvalidQueryException;
use EsOrm\Exceptions\MappingException;
use EsOrm\Exceptions\ModelNotFoundException;
use EsOrm\Exceptions\PaginationException;
use EsOrm\Exceptions\QueryException;
use EsOrm\Exceptions\ReindexException;
use EsOrm\Exceptions\SearchException;
use EsOrm\Exceptions\ValidationException;
use EsOrm\Tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Throwable;

final class EsOrmExceptionTest extends TestCase
{
    /**
     * @return array<string, array{0: class-string<EsOrmException>}>
     */
    public static function exceptionClassProvider(): array
    {
        return [
            'EsOrmException' => [EsOrmException::class],
            'BulkException' => [BulkException::class],
            'ConnectionException' => [ConnectionException::class],
            'DslException' => [DslException::class],
            'IndexException' => [IndexException::class],
            'InvalidQueryException' => [InvalidQueryException::class],
            'MappingException' => [MappingException::class],
            'ModelNotFoundException' => [ModelNotFoundException::class],
            'PaginationException' => [PaginationException::class],
            'QueryException' => [QueryException::class],
            'ReindexException' => [ReindexException::class],
            'SearchException' => [SearchException::class],
            'ValidationException' => [ValidationException::class],
        ];
    }

    #[DataProvider('exceptionClassProvider')]
    public function test_every_exception_extends_the_base_type(string $class): void
    {
        // Act
        $exception = new $class('boom');

        // Assert
        self::assertInstanceOf(EsOrmException::class, $exception);
        self::assertInstanceOf(RuntimeException::class, $exception);
        self::assertSame('boom', $exception->getMessage());
    }

    #[DataProvider('exceptionClassProvider')]
    public function test_the_default_context_is_empty(string $class): void
    {
        // Act
        $exception = new $class('boom');

        // Assert
        self::assertSame([], $exception->getContext());
    }

    public function test_from_previous_forwards_the_code_and_previous_exception(): void
    {
        // Arrange
        $previous = new RuntimeException('transport', 42);

        // Act
        $exception = EsOrmException::fromPrevious('wrapped', $previous);

        // Assert
        self::assertSame('wrapped', $exception->getMessage());
        self::assertSame(42, $exception->getCode());
        self::assertSame($previous, $exception->getPrevious());
    }

    public function test_from_previous_without_a_previous_exception(): void
    {
        // Act
        $exception = EsOrmException::fromPrevious('wrapped');

        // Assert
        self::assertSame(0, $exception->getCode());
        self::assertNull($exception->getPrevious());
    }

    public function test_model_not_found_exception_has_a_default_message(): void
    {
        // Act
        $exception = new ModelNotFoundException();

        // Assert
        self::assertSame('No results found.', $exception->getMessage());
        self::assertSame('', $exception->getModel());
        self::assertNull($exception->getId());
    }

    public function test_model_not_found_for_model_builds_the_message_and_carries_the_identifiers(): void
    {
        // Act
        $exception = ModelNotFoundException::forModel('App\\User', 7);

        // Assert
        self::assertSame('No results found for model [App\\User] with id [7].', $exception->getMessage());
        self::assertSame('App\\User', $exception->getModel());
        self::assertSame(7, $exception->getId());
    }

    public function test_model_not_found_for_model_json_encodes_non_scalar_ids(): void
    {
        // Act
        $exception = ModelNotFoundException::forModel('App\\User', ['a' => 1]);

        // Assert
        self::assertSame('No results found for model [App\\User] with id [{"a":1}].', $exception->getMessage());
        self::assertSame(['a' => 1], $exception->getId());
    }

    public function test_model_not_found_for_model_forwards_a_previous_exception(): void
    {
        // Arrange
        $previous = new RuntimeException('nope');

        // Act
        $exception = ModelNotFoundException::forModel('App\\User', 1, $previous);

        // Assert
        self::assertSame($previous, $exception->getPrevious());
    }

    public function test_bulk_exception_has_a_default_message(): void
    {
        // Act
        $exception = new BulkException();

        // Assert
        self::assertSame('Bulk operation failed.', $exception->getMessage());
        self::assertSame([], $exception->failures());
    }

    public function test_bulk_exception_from_failures_describes_the_count_and_carries_the_failures(): void
    {
        // Arrange
        $failures = [['index' => ['error' => 'boom']], ['delete' => ['error' => 'bang']]];

        // Act
        $exception = BulkException::fromFailures($failures);

        // Assert
        self::assertSame('2 bulk operation(s) failed.', $exception->getMessage());
        self::assertSame($failures, $exception->failures());
    }

    public function test_bulk_exception_from_failures_forwards_a_previous_exception(): void
    {
        // Arrange
        $previous = new RuntimeException('nope');

        // Act
        $exception = BulkException::fromFailures([['x' => []]], $previous);

        // Assert
        self::assertSame($previous, $exception->getPrevious());
    }

    public function test_validation_exception_carries_the_errors(): void
    {
        // Arrange
        $errors = ['name' => ['The name field is required.']];

        // Act
        $exception = new ValidationException('Invalid.', $errors);

        // Assert
        self::assertSame('Invalid.', $exception->getMessage());
        self::assertSame($errors, $exception->errors());
    }

    public function test_validation_exception_has_a_default_message(): void
    {
        // Act
        $exception = new ValidationException();

        // Assert
        self::assertSame('The given data was invalid.', $exception->getMessage());
        self::assertSame([], $exception->errors());
    }

    public function test_exceptions_can_be_thrown_and_caught_by_the_base_type(): void
    {
        // Arrange
        $caught = null;

        // Act
        try {
            throw MappingException::fromPrevious('bad mapping');
        } catch (EsOrmException $exception) {
            $caught = $exception;
        }

        // Assert
        self::assertInstanceOf(Throwable::class, $caught);
        self::assertSame('bad mapping', $caught->getMessage());
    }
}
