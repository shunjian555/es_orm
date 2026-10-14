<?php

declare(strict_types=1);

namespace EsOrm\Tests\Unit\Query;

use EsOrm\Query\AggregationBuilder;
use PHPUnit\Framework\TestCase as BaseTestCase;

/**
 * Covers the {@see AggregationBuilder} shorthands that are not registered
 * through the Builder-level DataProvider: dateHistogram, dateRange,
 * reverseNested, topHits and significantTerms.
 */
final class AggregationBuilderShorthandsTest extends BaseTestCase
{
    private AggregationBuilder $builder;

    protected function setUp(): void
    {
        parent::setUp();
        $this->builder = new AggregationBuilder();
    }

    public function test_it_registers_a_date_histogram_aggregation(): void
    {
        // Act
        $dsl = $this->builder->dateHistogram('sales', 'created_at', 'day', ['format' => 'yyyy-MM'])->toArray();

        // Assert
        self::assertEquals(['date_histogram' => ['field' => 'created_at', 'calendar_interval' => 'day', 'format' => 'yyyy-MM']], $dsl['sales']);
    }

    public function test_it_registers_a_date_range_aggregation(): void
    {
        // Act
        $dsl = $this->builder->dateRange('recent', 'created_at', [['from' => 'now-7d/d', 'to' => 'now/d']])->toArray();

        // Assert
        self::assertEquals(['date_range' => ['field' => 'created_at', 'ranges' => [['from' => 'now-7d/d', 'to' => 'now/d']]]], $dsl['recent']);
    }

    public function test_it_registers_a_reverse_nested_aggregation_with_a_path(): void
    {
        // Act
        $dsl = $this->builder->reverseNested('root', 'comments')->toArray();

        // Assert
        self::assertEquals(['reverse_nested' => ['path' => 'comments']], $dsl['root']);
    }

    public function test_it_registers_a_reverse_nested_aggregation_without_a_path(): void
    {
        // Act
        $dsl = $this->builder->reverseNested('root')->toArray();

        // Assert
        self::assertEquals(['reverse_nested' => (object) []], $dsl['root']);
    }

    public function test_it_registers_a_top_hits_aggregation_with_a_size(): void
    {
        // Act
        $dsl = $this->builder->topHits('top', 5, ['_source' => ['name']])->toArray();

        // Assert
        self::assertEquals(['top_hits' => ['size' => 5, '_source' => ['name']]], $dsl['top']);
    }

    public function test_it_registers_a_significant_terms_aggregation(): void
    {
        // Act
        $dsl = $this->builder->significantTerms('trending', 'tags')->toArray();

        // Assert
        self::assertEquals(['significant_terms' => ['field' => 'tags']], $dsl['trending']);
    }
}
