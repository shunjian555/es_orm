<?php

declare(strict_types=1);

namespace EsOrm\Tests\Unit\Support;

use EsOrm\Support\Config;
use PHPUnit\Framework\TestCase as BaseTestCase;

/**
 * Covers the ArrayAccess surface of {@see Config} (offsetSet / offsetGet /
 * offsetExists / offsetUnset) including dot notation keys.
 */
final class ConfigArrayAccessTest extends BaseTestCase
{
    public function test_it_sets_and_reads_values_through_array_access(): void
    {
        // Arrange
        $config = new Config();

        // Act
        $config['host'] = 'http://localhost:9200';
        $config['connections.default.username'] = 'elastic';

        // Assert
        self::assertSame('http://localhost:9200', $config['host']);
        self::assertSame('elastic', $config['connections.default.username']);
        self::assertSame('elastic', $config->get('connections.default.username'));
    }

    public function test_it_checks_existence_through_array_access(): void
    {
        // Arrange
        $config = new Config(['host' => 'http://localhost:9200', 'connections' => ['default' => []]]);

        // Assert
        self::assertTrue(isset($config['host']));
        self::assertTrue(isset($config['connections.default']));
        self::assertFalse(isset($config['missing']));
        self::assertNull($config['missing']);
    }

    public function test_it_removes_values_through_array_access(): void
    {
        // Arrange
        $config = new Config(['host' => 'http://localhost:9200', 'connections' => ['default' => ['username' => 'elastic']]]);

        // Act
        unset($config['connections.default.username']);

        // Assert
        self::assertFalse(isset($config['connections.default.username']));
        self::assertTrue(isset($config['connections.default']));
    }
}
