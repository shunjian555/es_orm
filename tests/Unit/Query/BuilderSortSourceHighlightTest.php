<?php

declare(strict_types=1);

namespace EsOrm\Tests\Unit\Query;

use EsOrm\Tests\Fixtures\User;
use EsOrm\Tests\TestCase;

/**
 * Covers sorting, source filtering and highlighting compilation.
 */
final class BuilderSortSourceHighlightTest extends TestCase
{
    public function test_order_by_defaults_to_ascending(): void
    {
        // Act
        $dsl = User::query()->orderBy('created_at')->toDsl();

        // Assert
        self::assertEquals([['created_at' => 'asc']], $dsl['sort']);
    }

    public function test_order_by_desc_builds_a_descending_sort(): void
    {
        // Act
        $dsl = User::query()->orderByDesc('created_at')->toDsl();

        // Assert
        self::assertEquals([['created_at' => 'desc']], $dsl['sort']);
    }

    public function test_order_by_asc_is_an_alias(): void
    {
        // Act
        $dsl = User::query()->orderByAsc('id')->toDsl();

        // Assert
        self::assertEquals([['id' => 'asc']], $dsl['sort']);
    }

    public function test_multiple_order_by_calls_are_preserved_in_order(): void
    {
        // Act
        $dsl = User::query()->orderBy('status', 'desc')->orderBy('age')->toDsl();

        // Assert
        self::assertSame([['status' => 'desc'], ['age' => 'asc']], $dsl['sort']);
    }

    public function test_order_by_accepts_a_mapping_of_fields(): void
    {
        // Act
        $dsl = User::query()->orderBy(['created_at' => 'desc', 'price' => 'asc'])->toDsl();

        // Assert
        self::assertSame([['created_at' => 'desc'], ['price' => 'asc']], $dsl['sort']);
    }

    public function test_order_by_accepts_a_plain_list_of_fields(): void
    {
        // Act
        $dsl = User::query()->orderBy(['created_at', 'price'])->toDsl();

        // Assert
        self::assertSame([['created_at' => 'asc'], ['price' => 'asc']], $dsl['sort']);
    }

    public function test_sort_supports_full_dsl_options(): void
    {
        // Act
        $dsl = User::query()->sort('price', 'asc', ['missing' => '_last', 'mode' => 'avg'])->toDsl();

        // Assert
        self::assertEquals([['price' => ['order' => 'asc', 'missing' => '_last', 'mode' => 'avg']]], $dsl['sort']);
    }

    public function test_sort_omits_the_node_when_no_ordering_is_configured(): void
    {
        // Act
        $dsl = User::query()->toDsl();

        // Assert
        self::assertArrayNotHasKey('sort', $dsl);
    }

    public function test_select_builds_the_source_includes_node(): void
    {
        // Act
        $dsl = User::query()->select(['id', 'name'])->toDsl();

        // Assert
        self::assertEquals(['includes' => ['id', 'name']], $dsl['_source']);
    }

    public function test_select_accepts_a_single_field_string(): void
    {
        // Act
        $dsl = User::query()->select('name')->toDsl();

        // Assert
        self::assertEquals(['includes' => ['name']], $dsl['_source']);
    }

    public function test_add_select_appends_and_deduplicates_fields(): void
    {
        // Act
        $dsl = User::query()->select(['name'])->addSelect(['email', 'name'])->toDsl();

        // Assert
        self::assertEquals(['includes' => ['name', 'email']], $dsl['_source']);
    }

    public function test_add_select_works_without_a_previous_select(): void
    {
        // Act
        $dsl = User::query()->addSelect(['name', 'email'])->toDsl();

        // Assert
        self::assertEquals(['includes' => ['name', 'email']], $dsl['_source']);
    }

    public function test_select_except_only_excludes_fields(): void
    {
        // Act
        $dsl = User::query()->selectExcept(['metadata', 'options'])->toDsl();

        // Assert
        self::assertEquals(['excludes' => ['metadata', 'options']], $dsl['_source']);
    }

    public function test_select_and_select_except_combine_into_one_node(): void
    {
        // Act
        $dsl = User::query()->select(['id', 'name'])->selectExcept(['metadata'])->toDsl();

        // Assert
        self::assertEquals(['includes' => ['id', 'name'], 'excludes' => ['metadata']], $dsl['_source']);
    }

    public function test_highlight_lists_fields_as_empty_objects(): void
    {
        // Act
        $dsl = User::query()->highlight(['title', 'body'])->toDsl();

        // Assert
        self::assertEquals([
            'fields' => ['title' => (object) [], 'body' => (object) []],
        ], $dsl['highlight']);
    }

    public function test_highlight_merges_global_and_per_field_options(): void
    {
        // Act
        $dsl = User::query()
            ->highlight(['title' => ['fragment_size' => 100]], [
                'pre_tags' => ['<b>'],
                'post_tags' => ['</b>'],
                'number_of_fragments' => 3,
            ])
            ->toDsl();

        // Assert
        self::assertEquals([
            'pre_tags' => ['<b>'],
            'post_tags' => ['</b>'],
            'number_of_fragments' => 3,
            'fields' => ['title' => ['fragment_size' => 100]],
        ], $dsl['highlight']);
    }

    public function test_highlight_is_omitted_when_not_configured(): void
    {
        // Act
        $dsl = User::query()->toDsl();

        // Assert
        self::assertArrayNotHasKey('highlight', $dsl);
    }
}
