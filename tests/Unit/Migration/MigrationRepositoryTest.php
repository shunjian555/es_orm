<?php

declare(strict_types=1);

namespace EsOrm\Tests\Unit\Migration;

use EsOrm\Migration\MigrationRepository;
use PHPUnit\Framework\TestCase as BaseTestCase;

/**
 * Covers the JSON-file backed migration repository: ran(), getBatch(),
 * lastBatch() plus the record/remove round-trip they build upon.
 */
final class MigrationRepositoryTest extends BaseTestCase
{
    private string $path;

    private MigrationRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->path = sys_get_temp_dir() . '/es_orm_repo_' . bin2hex(random_bytes(4)) . '.json';
        $this->repository = new MigrationRepository($this->path);
    }

    protected function tearDown(): void
    {
        if (is_file($this->path)) {
            @unlink($this->path);
        }

        parent::tearDown();
    }

    public function test_it_reports_nothing_ran_for_a_missing_repository_file(): void
    {
        // Assert
        self::assertSame([], $this->repository->all());
        self::assertSame([], $this->repository->ran());
        self::assertSame(0, $this->repository->lastBatch());
        self::assertFalse($this->repository->has('create_users'));
        self::assertNull($this->repository->getBatch('create_users'));
    }

    public function test_it_records_and_reports_migrations_in_insertion_order(): void
    {
        // Act
        $this->repository->record('create_users', 1);
        $this->repository->record('create_orders', 2);

        // Assert
        self::assertSame(['create_users', 'create_orders'], $this->repository->ran());
        self::assertSame(1, $this->repository->getBatch('create_users'));
        self::assertSame(2, $this->repository->getBatch('create_orders'));
        self::assertSame(2, $this->repository->lastBatch());
        self::assertTrue($this->repository->has('create_orders'));
    }

    public function test_it_removes_a_migration_and_recomputes_the_last_batch(): void
    {
        // Arrange
        $this->repository->record('create_users', 1);
        $this->repository->record('create_orders', 2);

        // Act
        $this->repository->remove('create_orders');

        // Assert
        self::assertSame(['create_users'], $this->repository->ran());
        self::assertNull($this->repository->getBatch('create_orders'));
        self::assertSame(1, $this->repository->lastBatch());
    }

    public function test_it_ignores_a_corrupted_repository_file(): void
    {
        // Arrange
        file_put_contents($this->path, '{"ran": "not-an-array"');

        // Assert
        self::assertSame([], $this->repository->ran());
        self::assertSame(0, $this->repository->lastBatch());
    }
}
