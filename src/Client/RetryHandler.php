<?php

declare(strict_types=1);

namespace EsOrm\Client;

use Elastic\Transport\Exception\TransportException;
use Psr\Http\Client\NetworkExceptionInterface;
use Throwable;

/**
 * Decides which failures are worth retrying and how long to wait.
 *
 * Only *transport* level problems and *overload* responses are retried:
 *  - connection resets, DNS failures, timeouts (PSR-18 network errors)
 *  - HTTP 429 (too many requests)
 *  - HTTP 5xx (server side errors)
 *
 * Everything else (400 validation errors, 404 not found, mapping conflicts,
 * ...) is deterministic and therefore never retried.
 */
final class RetryHandler
{
    /**
     * @param int   $maxRetries maximum number of *retries* (total attempts = maxRetries + 1)
     * @param int   $retryDelay initial delay in milliseconds
     * @param float $backoff    exponential backoff multiplier
     * @param int   $maxDelay   upper bound of a single delay in milliseconds
     */
    public function __construct(
        private readonly int $maxRetries = 3,
        private readonly int $retryDelay = 100,
        private readonly float $backoff = 2.0,
        private readonly int $maxDelay = 2000,
    ) {
    }

    /**
     * @param array<string, mixed> $config
     */
    public static function fromConfig(array $config): self
    {
        return new self(
            maxRetries: max(0, (int) ($config['max_retries'] ?? 3)),
            retryDelay: max(0, (int) ($config['retry_delay'] ?? 100)),
            backoff: max(1.0, (float) ($config['backoff'] ?? 2.0)),
            maxDelay: max(0, (int) ($config['max_delay'] ?? 2000)),
        );
    }

    public function maxRetries(): int
    {
        return $this->maxRetries;
    }

    public function isRetryable(Throwable $error): bool
    {
        if ($error instanceof NetworkExceptionInterface || $error instanceof TransportException) {
            return true;
        }

        $status = self::statusCode($error);

        if ($status !== null) {
            return $status === 429 || $status >= 500;
        }

        // Fallback for the 8.x client exception hierarchy, whose namespace is
        // `Elasticsearch\Common\Exceptions` instead of `Elastic\Elasticsearch\...`.
        return self::isLegacyRetryable($error);
    }

    /**
     * Delay before the given retry attempt (1-based), in milliseconds.
     */
    public function delayFor(int $attempt): int
    {
        if ($attempt < 1) {
            $attempt = 1;
        }

        $delay = $this->retryDelay * ($this->backoff ** ($attempt - 1));

        return (int) min($this->maxDelay, $delay);
    }

    /**
     * Execute the callback, retrying retryable failures with exponential
     * backoff.
     *
     * @template TReturn
     * @param callable(int): TReturn $callback receives the 1-based attempt number
     * @param callable(int, Throwable): void|null $onRetry notified before sleeping
     * @return TReturn
     * @throws Throwable the last error once retries are exhausted
     */
    public function run(callable $callback, ?callable $onRetry = null): mixed
    {
        $attempt = 0;

        while (true) {
            ++$attempt;

            try {
                return $callback($attempt);
            } catch (Throwable $error) {
                if ($attempt > $this->maxRetries || !$this->isRetryable($error)) {
                    throw $error;
                }

                if ($onRetry !== null) {
                    $onRetry($attempt, $error);
                }

                $delay = $this->delayFor($attempt);

                if ($delay > 0) {
                    usleep($delay * 1000);
                }
            }
        }
    }

    /**
     * Extract the HTTP status code of an Elasticsearch exception when possible.
     */
    public static function statusCode(Throwable $error): ?int
    {
        if (method_exists($error, 'getResponse')) {
            $response = $error->getResponse();

            if (is_object($response) && method_exists($response, 'getStatusCode')) {
                $code = $response->getStatusCode();

                if (is_int($code)) {
                    return $code;
                }
            }
        }

        return null;
    }

    private static function isLegacyRetryable(Throwable $error): bool
    {
        $class = $error::class;

        if (!str_starts_with($class, 'Elasticsearch\\Common\\Exceptions')) {
            return false;
        }

        foreach (['TransportException', 'ServerErrorResponseException', 'TooManyRequestsHttpException'] as $short) {
            if (is_a($error, 'Elasticsearch\\Common\\Exceptions\\' . $short)) {
                return true;
            }
        }

        return false;
    }
}
