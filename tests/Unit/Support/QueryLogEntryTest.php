<?php

declare(strict_types=1);

namespace EsOrm\Tests\Unit\Support;

use EsOrm\Support\Json;
use EsOrm\Support\QueryLogEntry;
use PHPUnit\Framework\TestCase;

final class QueryLogEntryTest extends TestCase
{
    private function entry(bool $successful = true, ?string $error = null): QueryLogEntry
    {
        return new QueryLogEntry(
            operation: 'search',
            method: 'POST',
            endpoint: '/users/_search',
            index: 'users',
            body: ['query' => ['match_all' => []]],
            duration: 1.0,
            successful: $successful,
            error: $error,
        );
    }

    public function test_it_exposes_its_properties(): void
    {
        $entry = $this->entry();

        self::assertSame('search', $entry->operation);
        self::assertSame('POST', $entry->method);
        self::assertSame('/users/_search', $entry->endpoint);
        self::assertSame('users', $entry->index);
        self::assertSame(['query' => ['match_all' => []]], $entry->body);
        self::assertSame(1.0, $entry->duration);
        self::assertTrue($entry->successful);
        self::assertNull($entry->error);
    }

    public function test_it_serializes_to_an_array(): void
    {
        $expected = [
            'operation' => 'search',
            'method' => 'POST',
            'endpoint' => '/users/_search',
            'index' => 'users',
            'body' => ['query' => ['match_all' => []]],
            'duration' => 1.0,
            'successful' => true,
            'error' => null,
        ];

        self::assertSame($expected, $this->entry()->toArray());
    }

    public function test_it_json_serializes_like_its_array_representation(): void
    {
        $entry = $this->entry();

        self::assertSame($entry->toArray(), $entry->jsonSerialize());
        self::assertJsonStringEqualsJsonString(
            '{"operation":"search","method":"POST","endpoint":"/users/_search","index":"users","body":{"query":{"match_all":[]}},"duration":1.0,"successful":true,"error":null}',
            Json::encode($entry),
        );
    }

    public function test_it_stringifies_a_successful_request(): void
    {
        self::assertSame('[search] POST /users/_search (1.00ms)', (string) $this->entry());
    }

    public function test_it_stringifies_a_failed_request(): void
    {
        self::assertSame(
            '[search] POST /users/_search -> FAILED: boom (1.00ms)',
            (string) $this->entry(false, 'boom'),
        );
    }
}
