<?php

declare(strict_types=1);

namespace EsOrm\Support;

use JsonSerializable;

/**
 * A single recorded Elasticsearch request: the development-time counterpart of
 * a SQL query log entry.
 */
final class QueryLogEntry implements JsonSerializable
{
    /**
     * @param array<string, mixed> $body
     */
    public function __construct(
        public readonly string $operation,
        public readonly string $method,
        public readonly string $endpoint,
        public readonly ?string $index = null,
        public readonly array $body = [],
        public readonly float $duration = 0.0,
        public readonly bool $successful = true,
        public readonly ?string $error = null,
    ) {
    }

    /**
     * @return array{
     *     operation: string,
     *     method: string,
     *     endpoint: string,
     *     index: string|null,
     *     body: array<string, mixed>,
     *     duration: float,
     *     successful: bool,
     *     error: string|null
     * }
     */
    public function toArray(): array
    {
        return [
            'operation' => $this->operation,
            'method' => $this->method,
            'endpoint' => $this->endpoint,
            'index' => $this->index,
            'body' => $this->body,
            'duration' => $this->duration,
            'successful' => $this->successful,
            'error' => $this->error,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    /**
     * Human readable one-liner, handy in logs.
     */
    public function __toString(): string
    {
        $duration = number_format($this->duration, 2);

        return sprintf(
            '[%s] %s %s%s (%sms)',
            $this->operation,
            $this->method,
            $this->endpoint,
            $this->successful ? '' : ' -> FAILED: ' . (string) $this->error,
            $duration,
        );
    }
}
