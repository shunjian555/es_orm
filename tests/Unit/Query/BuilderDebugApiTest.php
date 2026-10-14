<?php

declare(strict_types=1);

namespace EsOrm\Tests\Unit\Query;

use EsOrm\Contracts\ConnectionInterface;
use EsOrm\Tests\Fixtures\User;
use EsOrm\Tests\TestCase;
use Symfony\Component\VarDumper\VarDumper;

/**
 * Covers the debugging helpers (dump) and the connection resolution of the
 * query builder.
 */
final class BuilderDebugApiTest extends TestCase
{
    public function test_dump_prints_the_dsl_and_returns_the_builder(): void
    {
        // Arrange: capture VarDumper output instead of printing it.
        $captured = null;
        VarDumper::setHandler(static function (mixed $var) use (&$captured): void {
            $captured = $var;
        });

        try {
            $builder = User::query()->where('status', 'active');

            // Act
            $returned = $builder->dump();

            // Assert: the dumped payload is the full request body.
            self::assertSame($builder, $returned);
            self::assertIsArray($captured);
            self::assertSame($builder->toDsl(), $captured);
        } finally {
            VarDumper::setHandler(null);
        }
    }

    public function test_it_resolves_the_connection_of_the_model(): void
    {
        // Act
        $connection = User::query()->connectionInstance();

        // Assert: the same connection the manager exposes by default.
        self::assertInstanceOf(ConnectionInterface::class, $connection);
        self::assertSame($this->manager->connection(), $connection);
    }
}
