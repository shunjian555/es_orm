<?php

declare(strict_types=1);

namespace EsOrm\Tests\Unit\Support;

use EsOrm\Support\Config;
use PHPUnit\Framework\TestCase;

final class ConfigTest extends TestCase
{
    public function test_it_creates_a_config_from_an_array(): void
    {
        $config = Config::fromArray(['hosts' => ['http://localhost:9200']]);

        self::assertSame(['hosts' => ['http://localhost:9200']], $config->all());
    }

    public function test_it_gets_a_value_using_dot_notation(): void
    {
        $config = Config::fromArray(['retry' => ['max_retries' => 5]]);

        self::assertSame(5, $config->get('retry.max_retries'));
    }

    public function test_it_returns_the_default_for_a_missing_key(): void
    {
        $config = Config::fromArray([]);

        self::assertSame('fallback', $config->get('missing.key', 'fallback'));
    }

    public function test_it_sets_a_nested_value(): void
    {
        $config = Config::fromArray([]);
        $config->set('retry.max_retries', 7);

        self::assertSame(7, $config->get('retry.max_retries'));
    }

    public function test_it_reports_whether_a_key_exists(): void
    {
        $config = Config::fromArray(['enabled' => false]);

        self::assertTrue($config->has('enabled'));
        self::assertFalse($config->has('missing'));
    }

    public function test_it_forgets_a_key(): void
    {
        $config = Config::fromArray(['a' => 1, 'b' => 2]);
        $config->forget('a');

        self::assertSame(['b' => 2], $config->all());
    }

    public function test_it_supports_array_access(): void
    {
        $config = Config::fromArray(['a' => ['b' => 1]]);

        self::assertTrue(isset($config['a.b']));
        self::assertSame(1, $config['a.b']);

        $config['a.c'] = 2;
        self::assertSame(2, $config['a.c']);

        unset($config['a.b']);
        self::assertFalse(isset($config['a.b']));
    }

    public function test_it_loads_config_from_a_file(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'esorm_conf_');
        file_put_contents($path, '<?php return ["default" => "primary"];');

        try {
            $config = Config::fromFile($path);
        } finally {
            unlink($path);
        }

        self::assertSame('primary', $config->get('default'));
    }

    public function test_it_returns_an_empty_config_for_a_missing_file(): void
    {
        $config = Config::fromFile(sys_get_temp_dir() . '/esorm-does-not-exist-' . uniqid('', true) . '.php');

        self::assertSame([], $config->all());
    }
}
