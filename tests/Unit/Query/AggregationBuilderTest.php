<?php

declare(strict_types=1);

namespace EsOrm\Tests\Unit\Query;

use EsOrm\DSL\Aggregation\AvgAggregation;
use EsOrm\DSL\Aggregation\TermsAggregation;
use EsOrm\Query\AggregationBuilder;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for the aggregation collector (names + nesting + sub-aggregations).
 */
final class AggregationBuilderTest extends TestCase
{
    public function test_add_registers_a_named_aggregation_body(): void
    {
        // Act
        $aggs = (new AggregationBuilder())->add('by_status', new TermsAggregation('status'));

        // Assert
        self::assertEquals(['by_status' => ['terms' => ['field' => 'status']]], $aggs->toArray());
    }

    public function test_add_accepts_a_raw_array_body(): void
    {
        // Act
        $aggs = (new AggregationBuilder())->add('custom', ['cardinality' => ['field' => 'x']]);

        // Assert
        self::assertEquals(['custom' => ['cardinality' => ['field' => 'x']]], $aggs->toArray());
    }

    public function test_add_nests_sub_aggregations_from_the_callback(): void
    {
        // Act
        $aggs = (new AggregationBuilder())->add(
            'by_status',
            new TermsAggregation('status'),
            static function (AggregationBuilder $sub): void {
                $sub->avg('avg_age', 'age');
            },
        );

        // Assert
        self::assertEquals([
            'by_status' => [
                'terms' => ['field' => 'status'],
                'aggs' => ['avg_age' => ['avg' => ['field' => 'age']]],
            ],
        ], $aggs->toArray());
    }

    public function test_has_and_names_report_the_registered_aggregations(): void
    {
        // Arrange
        $aggs = new AggregationBuilder();

        // Assert
        self::assertTrue($aggs->isEmpty());

        // Act
        $aggs->add('a', new TermsAggregation('x'))->add('b', new AvgAggregation('y'));

        // Assert
        self::assertFalse($aggs->isEmpty());
        self::assertTrue($aggs->has('a'));
        self::assertFalse($aggs->has('c'));
        self::assertSame(['a', 'b'], $aggs->names());
    }

    public function test_first_and_entries_describe_the_registration_order(): void
    {
        // Arrange
        $aggs = (new AggregationBuilder())
            ->add('a', new TermsAggregation('x'))
            ->add('b', new AvgAggregation('y'));

        // Act
        $first = $aggs->first();
        $entries = $aggs->entries();

        // Assert
        self::assertSame('a', $first['name']);
        self::assertEquals(['terms' => ['field' => 'x']], $first['body']);
        self::assertCount(2, $entries);
        self::assertSame('b', $entries[1]['name']);
    }

    public function test_first_returns_null_when_nothing_is_registered(): void
    {
        // Assert
        self::assertNull((new AggregationBuilder())->first());
    }

    public function test_forget_removes_an_aggregation(): void
    {
        // Arrange
        $aggs = (new AggregationBuilder())->add('a', new TermsAggregation('x'));

        // Act
        $aggs->forget('a');

        // Assert
        self::assertFalse($aggs->has('a'));
        self::assertTrue($aggs->isEmpty());
    }

    public function test_raw_registers_a_raw_aggregation(): void
    {
        // Act
        $aggs = (new AggregationBuilder())->raw('custom', ['cardinality' => ['field' => 'x']]);

        // Assert
        self::assertEquals(['custom' => ['cardinality' => ['field' => 'x']]], $aggs->toArray());
    }

    public function test_filter_wraps_a_raw_query_array(): void
    {
        // Act
        $aggs = (new AggregationBuilder())->filter('active', ['term' => ['status' => 'active']]);

        // Assert
        self::assertEquals(['active' => ['filter' => ['term' => ['status' => 'active']]]], $aggs->toArray());
    }

    public function test_filter_passes_through_an_aggregation_object(): void
    {
        // Act
        $aggs = (new AggregationBuilder())->filter('avg_age', new AvgAggregation('age'));

        // Assert
        self::assertEquals(['avg_age' => ['avg' => ['field' => 'age']]], $aggs->toArray());
    }

    public function test_terms_shorthand_registers_the_aggregation(): void
    {
        // Act
        $aggs = (new AggregationBuilder())->terms('by_status', 'status', ['size' => 10]);

        // Assert
        self::assertEquals(['by_status' => ['terms' => ['field' => 'status', 'size' => 10]]], $aggs->toArray());
    }
}
