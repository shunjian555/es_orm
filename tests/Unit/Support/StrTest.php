<?php

declare(strict_types=1);

namespace EsOrm\Tests\Unit\Support;

use EsOrm\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class StrTest extends TestCase
{
    /**
     * @return array<string, array{string, string}>
     */
    public static function snakeProvider(): array
    {
        return [
            'studly to snake' => ['FooBar', 'foo_bar'],
            'camel to snake' => ['fooBar', 'foo_bar'],
            'spaces to snake' => ['foo bar', 'foo_bar'],
            'already snake' => ['foo_bar', 'foo_bar'],
            'acronym splits each uppercase' => ['FOO', 'f_o_o'],
            'multi word' => ['FooBarBaz', 'foo_bar_baz'],
            'empty stays empty' => ['', ''],
        ];
    }

    #[DataProvider('snakeProvider')]
    public function test_it_converts_to_snake_case(string $value, string $expected): void
    {
        self::assertSame($expected, Str::snake($value));
    }

    public function test_it_converts_to_snake_case_with_a_custom_delimiter(): void
    {
        self::assertSame('foo-bar', Str::snake('fooBar', '-'));
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function camelProvider(): array
    {
        return [
            'snake to camel' => ['foo_bar', 'fooBar'],
            'kebab to camel' => ['foo-bar', 'fooBar'],
            'studly to camel' => ['FooBar', 'fooBar'],
            'upper snake to camel' => ['FOO_BAR', 'fOOBAR'],
        ];
    }

    #[DataProvider('camelProvider')]
    public function test_it_converts_to_camel_case(string $value, string $expected): void
    {
        self::assertSame($expected, Str::camel($value));
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function studlyProvider(): array
    {
        return [
            'snake to studly' => ['foo_bar', 'FooBar'],
            'kebab to studly' => ['foo-bar', 'FooBar'],
            'spaces to studly' => ['foo bar', 'FooBar'],
            'camel to studly' => ['fooBar', 'FooBar'],
        ];
    }

    #[DataProvider('studlyProvider')]
    public function test_it_converts_to_studly_case(string $value, string $expected): void
    {
        self::assertSame($expected, Str::studly($value));
    }

    public function test_it_checks_contains_with_a_single_needle(): void
    {
        self::assertTrue(Str::contains('hello world', 'world'));
        self::assertFalse(Str::contains('hello world', 'planet'));
    }

    public function test_it_checks_contains_with_multiple_needles(): void
    {
        self::assertTrue(Str::contains('hello world', ['nope', 'world']));
        self::assertFalse(Str::contains('hello world', ['nope', 'planet']));
        self::assertFalse(Str::contains('hello', ['']));
    }

    public function test_it_checks_starts_with(): void
    {
        self::assertTrue(Str::startsWith('es-orm', 'es'));
        self::assertTrue(Str::startsWith('es-orm', ['orm', 'es']));
        self::assertFalse(Str::startsWith('es-orm', 'orm'));
        self::assertFalse(Str::startsWith('es-orm', ['']));
    }

    public function test_it_checks_ends_with(): void
    {
        self::assertTrue(Str::endsWith('es-orm', 'orm'));
        self::assertTrue(Str::endsWith('es-orm', ['es', 'orm']));
        self::assertFalse(Str::endsWith('es-orm', 'es'));
        self::assertFalse(Str::endsWith('es-orm', ['']));
    }
}
