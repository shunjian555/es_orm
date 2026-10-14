<?php

declare(strict_types=1);

namespace EsOrm\Tests\Unit\Client;

use EsOrm\Client\RetryHandler;
use PHPUnit\Framework\TestCase as BaseTestCase;
use RuntimeException;
use stdClass;

/**
 * Covers {@see RetryHandler::statusCode()}, which extracts the HTTP status
 * from Elasticsearch exceptions that expose a PSR-7 style response.
 */
final class RetryHandlerStatusCodeTest extends BaseTestCase
{
    public function test_it_extracts_the_status_code_from_an_elasticsearch_exception(): void
    {
        // Arrange
        $error = new class ('too many requests') extends RuntimeException {
            public function getResponse(): object
            {
                return new class () {
                    public function getStatusCode(): int
                    {
                        return 429;
                    }
                };
            }
        };

        // Act + Assert
        self::assertSame(429, RetryHandler::statusCode($error));
    }

    public function test_it_returns_null_when_the_response_has_no_status_code(): void
    {
        // Arrange
        $error = new class ('boom') extends RuntimeException {
            public function getResponse(): object
            {
                return new stdClass();
            }
        };

        // Act + Assert
        self::assertNull(RetryHandler::statusCode($error));
    }

    public function test_it_returns_null_for_a_plain_throwable(): void
    {
        // Arrange
        $error = new RuntimeException('transport failure');

        // Act + Assert
        self::assertNull(RetryHandler::statusCode($error));
    }

    public function test_it_returns_null_when_get_response_returns_a_scalar(): void
    {
        // Arrange
        $error = new class ('boom') extends RuntimeException {
            public function getResponse(): int
            {
                return 500;
            }
        };

        // Act + Assert
        self::assertNull(RetryHandler::statusCode($error));
    }
}
