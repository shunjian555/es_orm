<?php

declare(strict_types=1);

namespace EsOrm\Tests\Unit\Dsl\Sort;

use EsOrm\DSL\Sort\FieldSort;
use PHPUnit\Framework\TestCase;

final class FieldSortTest extends TestCase
{
    public function test_it_builds_a_scalar_sort_with_the_default_ascending_direction(): void
    {
        self::assertSame(['price' => 'asc'], (new FieldSort('price'))->toArray());
    }

    public function test_it_builds_a_scalar_sort_with_a_descending_direction(): void
    {
        self::assertSame(['price' => 'desc'], (new FieldSort('price', 'desc'))->toArray());
    }

    public function test_it_builds_the_extended_form_when_options_are_given(): void
    {
        self::assertSame([
            'price' => ['order' => 'asc', 'missing' => '_last'],
        ], (new FieldSort('price', 'asc', ['missing' => '_last']))->toArray());
    }

    public function test_it_merges_several_sort_options(): void
    {
        self::assertSame([
            'published_at' => ['order' => 'desc', 'unmapped_type' => 'long', 'missing' => '_last'],
        ], (new FieldSort('published_at', 'desc', ['unmapped_type' => 'long', 'missing' => '_last']))->toArray());
    }

    public function test_it_keeps_a_unicode_field_name_verbatim(): void
    {
        self::assertSame(['创建时间' => 'asc'], (new FieldSort('创建时间'))->toArray());
    }

    public function test_a_sort_serialises_to_the_same_array_as_to_array(): void
    {
        $sort = new FieldSort('price', 'desc', ['missing' => '_last']);

        self::assertSame($sort->toArray(), $sort->jsonSerialize());
    }
}
