<?php

declare(strict_types=1);

namespace EsOrm\Tests\Unit\Model;

use EsOrm\Tests\Fixtures\User;
use EsOrm\Tests\TestCase;

/**
 * Covers {@see \EsOrm\Model\Model::destroy()}: it must issue a bulk delete
 * with one NDJSON action per id against the model index.
 */
final class ModelDestroyTest extends TestCase
{
    /**
     * @return list<array<string, mixed>>
     */
    private function bulkLines(): array
    {
        $body = $this->client->lastParams('bulk')['body'];
        self::assertIsString($body);

        $lines = array_values(array_filter(explode("\n", $body), static fn (string $line): bool => $line !== ''));
        $decoded = [];
        foreach ($lines as $line) {
            $decoded[] = json_decode($line, true, 512, JSON_THROW_ON_ERROR);
        }

        return $decoded;
    }

    public function test_it_bulk_deletes_multiple_documents_by_id(): void
    {
        // Act
        $deleted = User::destroy('1', '2');

        // Assert: one delete action per id, targeted at the model index.
        self::assertSame(2, $deleted);
        self::assertSame(1, $this->client->callCount('bulk'));

        $lines = $this->bulkLines();
        self::assertCount(2, $lines);
        self::assertEquals(['delete' => ['_index' => 'users', '_id' => '1']], $lines[0]);
        self::assertEquals(['delete' => ['_index' => 'users', '_id' => '2']], $lines[1]);
    }

    public function test_it_accepts_an_array_of_ids(): void
    {
        // Act
        $deleted = User::destroy(['7']);

        // Assert
        self::assertSame(1, $deleted);
        self::assertEquals(['delete' => ['_index' => 'users', '_id' => '7']], $this->bulkLines()[0]);
    }
}
