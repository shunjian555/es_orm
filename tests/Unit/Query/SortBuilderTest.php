<?php

declare(strict_types=1);

namespace EsOrm\Tests\Unit\Query;

use EsOrm\DSL\Sort\FieldSort;
use EsOrm\Query\SortBuilder;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for the sort clause collector.
 */
final class SortBuilderTest extends TestCase
{
    public function test_add_builds_a_scalar_sort(): void
    {
        // Act
        $sorts = (new SortBuilder())->add('created_at', 'desc');

        // Assert
        self::assertSame([['created_at' => 'desc']], $sorts->toArray());
    }

    public function test_add_defaults_to_ascending(): void
    {
        // Act
        $sorts = (new SortBuilder())->add('created_at');

        // Assert
        self::assertSame([['created_at' => 'asc']], $sorts->all());
    }

    public function test_add_supports_full_field_options(): void
    {
        // Act
        $sorts = (new SortBuilder())->add('price', 'asc', ['missing' => '_last']);

        // Assert
        self::assertEquals([['price' => ['order' => 'asc', 'missing' => '_last']]], $sorts->toArray());
    }

    public function test_add_accepts_a_mapping_of_fields(): void
    {
        // Act
        $sorts = (new SortBuilder())->add(['a' => 'desc', 'b' => 'asc']);

        // Assert
        self::assertSame([['a' => 'desc'], ['b' => 'asc']], $sorts->toArray());
    }

    public function test_add_accepts_a_plain_list_of_fields(): void
    {
        // Act
        $sorts = (new SortBuilder())->add(['a', 'b']);

        // Assert
        self::assertSame([['a' => 'asc'], ['b' => 'asc']], $sorts->toArray());
    }

    public function test_add_accepts_a_dsl_object(): void
    {
        // Act
        $sorts = (new SortBuilder())->add(new FieldSort('price', 'desc'));

        // Assert
        self::assertSame([['price' => 'desc']], $sorts->toArray());
    }

    public function test_add_accepts_a_list_containing_dsl_objects(): void
    {
        // Act
        $sorts = (new SortBuilder())->add([new FieldSort('price', 'desc')]);

        // Assert
        self::assertSame([['price' => 'desc']], $sorts->toArray());
    }

    public function test_is_empty_reflects_the_collected_sorts(): void
    {
        // Arrange
        $sorts = new SortBuilder();

        // Assert
        self::assertTrue($sorts->isEmpty());

        // Act
        $sorts->add('id');

        // Assert
        self::assertFalse($sorts->isEmpty());
    }

    public function test_has_score_detects_the_score_field(): void
    {
        // Act
        $withScore = (new SortBuilder())->add('_score', 'desc');
        $withoutScore = (new SortBuilder())->add('id');

        // Assert
        self::assertTrue($withScore->hasScore());
        self::assertFalse($withoutScore->hasScore());
    }

    public function test_fields_lists_every_sorted_field_in_order(): void
    {
        // Act
        $sorts = (new SortBuilder())->add('a')->add('b', 'desc');

        // Assert
        self::assertSame(['a', 'b'], $sorts->fields());
    }

    public function test_replace_swaps_every_sort_clause(): void
    {
        // Act
        $sorts = (new SortBuilder())->add('a')->replace([['z' => 'desc']]);

        // Assert
        self::assertSame([['z' => 'desc']], $sorts->toArray());
    }

    public function test_ensure_tie_breaker_adds_a_sort_only_when_empty(): void
    {
        // Act
        $empty = (new SortBuilder())->ensureTieBreaker('id');
        $populated = (new SortBuilder())->add('created_at')->ensureTieBreaker('id');

        // Assert
        self::assertSame([['id' => 'asc']], $empty->toArray());
        self::assertSame([['created_at' => 'asc']], $populated->toArray());
    }
}
