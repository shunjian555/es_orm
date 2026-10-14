<?php

declare(strict_types=1);

namespace EsOrm\Tests\Unit\Support;

use EsOrm\Support\Arr;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ArrTest extends TestCase
{
    /**
     * @return array<string, array{mixed, array<array-key, mixed>}>
     */
    public static function wrapProvider(): array
    {
        return [
            'null becomes empty array' => [null, []],
            'scalar is wrapped' => ['tom', ['tom']],
            'integer is wrapped' => [42, [42]],
            'array is returned as is' => [['a' => 1], ['a' => 1]],
            'empty array stays empty' => [[], []],
        ];
    }

    /**
     * @param array<array-key, mixed> $expected
     */
    #[DataProvider('wrapProvider')]
    public function test_it_wraps_a_value(mixed $value, array $expected): void
    {
        self::assertSame($expected, Arr::wrap($value));
    }

    public function test_it_gets_a_top_level_key(): void
    {
        self::assertSame('bar', Arr::get(['foo' => 'bar'], 'foo'));
    }

    public function test_it_gets_a_nested_key_using_dot_notation(): void
    {
        self::assertSame('baz', Arr::get(['a' => ['b' => ['c' => 'baz']]], 'a.b.c'));
    }

    public function test_it_returns_the_default_for_a_missing_key(): void
    {
        self::assertSame('fallback', Arr::get(['a' => 1], 'a.b', 'fallback'));
    }

    public function test_it_prefers_a_literal_key_over_dot_notation(): void
    {
        self::assertSame('literal', Arr::get(['a.b' => 'literal'], 'a.b'));
    }

    public function test_it_returns_the_whole_array_for_a_null_key(): void
    {
        $array = ['a' => 1];

        self::assertSame($array, Arr::get($array, null));
    }

    public function test_it_gets_an_integer_key(): void
    {
        self::assertSame('x', Arr::get(['x'], 0));
    }

    public function test_it_sets_a_top_level_key(): void
    {
        $array = [];
        Arr::set($array, 'foo', 'bar');

        self::assertSame(['foo' => 'bar'], $array);
    }

    public function test_it_sets_a_nested_key_creating_intermediate_arrays(): void
    {
        $array = [];
        Arr::set($array, 'a.b.c', 1);

        self::assertSame(['a' => ['b' => ['c' => 1]]], $array);
    }

    public function test_it_overwrites_a_scalar_while_setting_nested(): void
    {
        $array = ['a' => 'scalar'];
        Arr::set($array, 'a.b', 2);

        self::assertSame(['a' => ['b' => 2]], $array);
    }

    public function test_it_does_nothing_when_setting_a_null_key(): void
    {
        $array = ['a' => 1];
        Arr::set($array, null, 2);

        self::assertSame(['a' => 1], $array);
    }

    public function test_it_reports_a_single_existing_key(): void
    {
        self::assertTrue(Arr::has(['a' => 1], 'a'));
    }

    public function test_it_reports_a_single_missing_key(): void
    {
        self::assertFalse(Arr::has(['a' => 1], 'b'));
    }

    public function test_it_requires_every_key_of_an_array_to_exist(): void
    {
        self::assertTrue(Arr::has(['a' => 1, 'b' => 2], ['a', 'b']));
        self::assertFalse(Arr::has(['a' => 1], ['a', 'b']));
    }

    public function test_it_treats_a_null_value_as_an_existing_key(): void
    {
        self::assertTrue(Arr::has(['a' => null], 'a'));
    }

    public function test_it_forgets_a_top_level_key(): void
    {
        $array = ['a' => 1, 'b' => 2];
        Arr::forget($array, 'a');

        self::assertSame(['b' => 2], $array);
    }

    public function test_it_forgets_a_nested_key(): void
    {
        $array = ['a' => ['b' => 1, 'c' => 2]];
        Arr::forget($array, 'a.b');

        self::assertSame(['a' => ['c' => 2]], $array);
    }

    public function test_it_silently_ignores_forgetting_a_missing_nested_key(): void
    {
        $array = ['a' => 1];
        Arr::forget($array, 'x.y.z');

        self::assertSame(['a' => 1], $array);
    }

    public function test_it_keeps_only_the_requested_keys(): void
    {
        self::assertSame(['a' => 1], Arr::only(['a' => 1, 'b' => 2], 'a'));
        self::assertSame(['a' => 1], Arr::only(['a' => 1, 'b' => 2], ['a', 'c']));
    }

    public function test_it_removes_the_requested_keys(): void
    {
        self::assertSame(['b' => 2], Arr::except(['a' => 1, 'b' => 2], 'a'));
        self::assertSame([], Arr::except(['a' => 1], ['a', 'b']));
    }

    public function test_it_detects_an_associative_array(): void
    {
        self::assertTrue(Arr::isAssoc(['a' => 1]));
    }

    public function test_it_detects_a_list(): void
    {
        self::assertFalse(Arr::isAssoc([1, 2, 3]));
    }

    public function test_it_treats_an_empty_array_as_non_associative(): void
    {
        self::assertFalse(Arr::isAssoc([]));
    }
}
