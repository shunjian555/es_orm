<?php

declare(strict_types=1);

namespace EsOrm\Tests\Unit\Client;

use Elastic\Elasticsearch\Exception\ClientResponseException;
use Elastic\Elasticsearch\Exception\ServerResponseException;
use Elastic\Transport\Exception\CurlException;
use EsOrm\Client\RetryHandler;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Throwable;

final class RetryHandlerTest extends TestCase
{
    /**
     * @return array<string, array{Throwable, bool}>
     */
    public static function retryableProvider(): array
    {
        return [
            'transport exception' => [new CurlException('curl failure'), true],
            'psr-18 network exception' => [new ConnectException('refused', new Request('GET', '/')), true],
            'http 429' => [self::httpError(429), true],
            'http 500' => [self::httpError(500), true],
            'http 503' => [self::httpError(503), true],
            'http 400' => [self::httpError(400), false],
            'http 404' => [self::httpError(404), false],
            'plain exception without a response' => [new RuntimeException('nope'), false],
        ];
    }

    #[DataProvider('retryableProvider')]
    public function test_it_decides_whether_an_error_is_retryable(Throwable $error, bool $expected): void
    {
        self::assertSame($expected, (new RetryHandler())->isRetryable($error));
    }

    public function test_it_computes_an_exponential_delay(): void
    {
        $handler = new RetryHandler(3, 100, 2.0, 2000);

        self::assertSame(100, $handler->delayFor(1));
        self::assertSame(200, $handler->delayFor(2));
        self::assertSame(400, $handler->delayFor(3));
        self::assertSame(800, $handler->delayFor(4));
        self::assertSame(1600, $handler->delayFor(5));
    }

    public function test_it_caps_the_delay_at_max_delay(): void
    {
        $handler = new RetryHandler(10, 100, 2.0, 500);

        self::assertSame(500, $handler->delayFor(4));
        self::assertSame(500, $handler->delayFor(20));
    }

    public function test_a_zero_delay_keeps_every_delay_at_zero(): void
    {
        $handler = new RetryHandler(3, 0, 2.0, 2000);

        self::assertSame(0, $handler->delayFor(1));
        self::assertSame(0, $handler->delayFor(5));
    }

    public function test_it_treats_a_non_positive_attempt_as_the_first_one(): void
    {
        $handler = new RetryHandler(3, 100, 2.0, 2000);

        self::assertSame(100, $handler->delayFor(0));
        self::assertSame(100, $handler->delayFor(-5));
    }

    public function test_run_returns_the_value_without_retrying_on_success(): void
    {
        $attempts = 0;

        $result = (new RetryHandler(3, 0))->run(function (int $attempt) use (&$attempts): string {
            $attempts = $attempt;

            return 'value';
        });

        self::assertSame('value', $result);
        self::assertSame(1, $attempts);
    }

    public function test_run_retries_a_retryable_error_until_it_succeeds(): void
    {
        $handler = new RetryHandler(2, 0);
        $attempts = 0;

        $result = $handler->run(function (int $attempt) use (&$attempts): string {
            $attempts = $attempt;

            if ($attempt < 3) {
                throw self::httpError(503);
            }

            return 'recovered';
        });

        self::assertSame('recovered', $result);
        self::assertSame(3, $attempts);
    }

    public function test_run_does_not_retry_a_non_retryable_error(): void
    {
        $handler = new RetryHandler(3, 0);
        $attempts = 0;

        try {
            $handler->run(function (int $attempt) use (&$attempts): never {
                $attempts = $attempt;

                throw self::httpError(400);
            });
            self::fail('Expected the error to be rethrown.');
        } catch (ClientResponseException $error) {
            self::assertSame(400, $error->getCode());
        }

        self::assertSame(1, $attempts);
    }

    public function test_run_rethrows_after_exhausting_retries(): void
    {
        $handler = new RetryHandler(2, 0);
        $attempts = 0;

        try {
            $handler->run(function (int $attempt) use (&$attempts): never {
                $attempts = $attempt;

                throw self::httpError(429);
            });
            self::fail('Expected the error to be rethrown.');
        } catch (ClientResponseException) {
            // expected
        }

        self::assertSame(3, $attempts);
    }

    public function test_run_notifies_the_on_retry_callback(): void
    {
        $handler = new RetryHandler(2, 0);
        $retries = [];

        try {
            $handler->run(
                function (): never {
                    throw self::httpError(500);
                },
                static function (int $attempt, Throwable $error) use (&$retries): void {
                    $retries[] = [$attempt, $error->getCode()];
                },
            );
            self::fail('Expected the error to be rethrown.');
        } catch (ServerResponseException) {
            // expected
        }

        self::assertSame([[1, 500], [2, 500]], $retries);
    }

    public function test_from_config_reads_and_clamps_the_values(): void
    {
        $handler = RetryHandler::fromConfig([
            'max_retries' => 5,
            'retry_delay' => 10,
            'backoff' => 3.0,
            'max_delay' => 100,
        ]);

        self::assertSame(5, $handler->maxRetries());
        self::assertSame(10, $handler->delayFor(1));
        self::assertSame(30, $handler->delayFor(2));
        self::assertSame(100, $handler->delayFor(4));
    }

    public function test_from_config_clamps_negative_values(): void
    {
        $handler = RetryHandler::fromConfig([
            'max_retries' => -3,
            'retry_delay' => -10,
            'backoff' => 0.5,
            'max_delay' => -5,
        ]);

        self::assertSame(0, $handler->maxRetries());
        self::assertSame(0, $handler->delayFor(1));
    }

    public function test_from_config_clamps_backoff_to_at_least_one(): void
    {
        $handler = RetryHandler::fromConfig(['retry_delay' => 10, 'backoff' => 0.25, 'max_delay' => 1000]);

        self::assertSame(10, $handler->delayFor(1));
        self::assertSame(10, $handler->delayFor(2));
    }

    public function test_from_config_uses_defaults_for_an_empty_array(): void
    {
        $handler = RetryHandler::fromConfig([]);

        self::assertSame(3, $handler->maxRetries());
        self::assertSame(100, $handler->delayFor(1));
        self::assertSame(2000, $handler->delayFor(10));
    }

    private static function httpError(int $status): ClientResponseException|ServerResponseException
    {
        $response = new Response($status, ['Content-Type' => 'application/json'], '{}');
        $exception = $status >= 500
            ? new ServerResponseException('server error', $status)
            : new ClientResponseException('client error', $status);

        return $exception->setResponse($response);
    }
}
