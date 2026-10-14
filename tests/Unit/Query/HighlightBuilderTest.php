<?php

declare(strict_types=1);

namespace EsOrm\Tests\Unit\Query;

use EsOrm\Query\HighlightBuilder;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for the highlight clause collector.
 */
final class HighlightBuilderTest extends TestCase
{
    public function test_add_lists_fields_as_empty_objects(): void
    {
        // Act
        $highlight = (new HighlightBuilder())->add(['title', 'body']);

        // Assert
        self::assertEquals(['fields' => ['title' => (object) [], 'body' => (object) []]], $highlight->toArray());
    }

    public function test_add_wraps_a_single_string_field(): void
    {
        // Act
        $highlight = (new HighlightBuilder())->add('title');

        // Assert
        self::assertEquals(['fields' => ['title' => (object) []]], $highlight->toArray());
    }

    public function test_add_supports_per_field_options(): void
    {
        // Act
        $highlight = (new HighlightBuilder())->add(['title' => ['fragment_size' => 100]]);

        // Assert
        self::assertEquals(['fields' => ['title' => ['fragment_size' => 100]]], $highlight->toArray());
    }

    public function test_add_merges_global_options(): void
    {
        // Act
        $highlight = (new HighlightBuilder())->add(['title'], ['pre_tags' => ['<b>'], 'post_tags' => ['</b>']]);

        // Assert
        self::assertEquals([
            'pre_tags' => ['<b>'],
            'post_tags' => ['</b>'],
            'fields' => ['title' => (object) []],
        ], $highlight->toArray());
    }

    public function test_options_builds_a_fields_less_highlight(): void
    {
        // Act
        $highlight = (new HighlightBuilder())->options(['number_of_fragments' => 3]);

        // Assert
        self::assertEquals(['number_of_fragments' => 3], $highlight->toArray());
    }

    public function test_add_is_accumulative(): void
    {
        // Act
        $highlight = (new HighlightBuilder())
            ->add('title')
            ->add(['body' => ['fragment_size' => 200]]);

        // Assert
        self::assertEquals([
            'fields' => ['title' => (object) [], 'body' => ['fragment_size' => 200]],
        ], $highlight->toArray());
    }

    public function test_is_empty_and_to_array_handle_the_empty_state(): void
    {
        // Arrange
        $highlight = new HighlightBuilder();

        // Assert
        self::assertTrue($highlight->isEmpty());
        self::assertSame([], $highlight->toArray());
    }

    public function test_is_empty_is_false_once_a_field_is_added(): void
    {
        // Act
        $highlight = (new HighlightBuilder())->add('title');

        // Assert
        self::assertFalse($highlight->isEmpty());
    }
}
