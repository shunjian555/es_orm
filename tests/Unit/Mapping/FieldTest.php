<?php

declare(strict_types=1);

namespace EsOrm\Tests\Unit\Mapping;

use EsOrm\Exceptions\MappingException;
use EsOrm\Mapping\Field;
use EsOrm\Tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

final class FieldTest extends TestCase
{
    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function typeProvider(): array
    {
        return [
            'text' => ['text', 'text'],
            'keyword' => ['keyword', 'keyword'],
            'long' => ['long', 'long'],
            'integer' => ['integer', 'integer'],
            'short' => ['short', 'short'],
            'byte' => ['byte', 'byte'],
            'double' => ['double', 'double'],
            'float' => ['float', 'float'],
            'boolean' => ['boolean', 'boolean'],
            'date' => ['date', 'date'],
            'object' => ['object', 'object'],
            'nested' => ['nested', 'nested'],
            'geoPoint' => ['geoPoint', 'geo_point'],
            'geoShape' => ['geoShape', 'geo_shape'],
            'ip' => ['ip', 'ip'],
            'binary' => ['binary', 'binary'],
            'completion' => ['completion', 'completion'],
            'searchAsYouType' => ['searchAsYouType', 'search_as_you_type'],
            'denseVector' => ['denseVector', 'dense_vector'],
            'flattened' => ['flattened', 'flattened'],
        ];
    }

    #[DataProvider('typeProvider')]
    public function test_it_builds_a_field_of_the_expected_type(string $factory, string $expected): void
    {
        // Arrange / Act
        $field = Field::$factory();

        // Assert
        self::assertSame($expected, $field->getType());
        self::assertSame(['type' => $expected], $field->toArray());
    }

    public function test_of_builds_an_arbitrary_field_type(): void
    {
        // Act
        $field = Field::of('rank_feature');

        // Assert
        self::assertSame('rank_feature', $field->getType());
        self::assertSame(['type' => 'rank_feature'], $field->toArray());
    }

    public function test_it_rejects_an_empty_field_type(): void
    {
        // Assert
        $this->expectException(MappingException::class);
        $this->expectExceptionMessage('A mapping field type must not be empty.');

        // Act
        Field::of('');
    }

    public function test_constructor_options_are_serialised_before_fluent_options(): void
    {
        // Act
        $field = Field::keyword(['ignore_above' => 256]);

        // Assert
        self::assertSame(['type' => 'keyword', 'ignore_above' => 256], $field->toArray());
    }

    public function test_analyzer_is_recorded(): void
    {
        // Act
        $field = Field::text()->analyzer('standard');

        // Assert
        self::assertSame(['type' => 'text', 'analyzer' => 'standard'], $field->toArray());
    }

    public function test_search_analyzer_is_recorded(): void
    {
        // Act
        $field = Field::text()->searchAnalyzer('whitespace');

        // Assert
        self::assertSame(['type' => 'text', 'search_analyzer' => 'whitespace'], $field->toArray());
    }

    public function test_normalizer_is_recorded(): void
    {
        // Act
        $field = Field::keyword()->normalizer('lowercase');

        // Assert
        self::assertSame(['type' => 'keyword', 'normalizer' => 'lowercase'], $field->toArray());
    }

    public function test_format_is_recorded(): void
    {
        // Act
        $field = Field::date()->format('yyyy-MM-dd');

        // Assert
        self::assertSame(['type' => 'date', 'format' => 'yyyy-MM-dd'], $field->toArray());
    }

    public function test_index_flag_is_recorded(): void
    {
        // Act
        $field = Field::text()->index(false);

        // Assert
        self::assertSame(['type' => 'text', 'index' => false], $field->toArray());
    }

    public function test_doc_values_flag_is_recorded(): void
    {
        // Act
        $field = Field::keyword()->docValues(false);

        // Assert
        self::assertSame(['type' => 'keyword', 'doc_values' => false], $field->toArray());
    }

    public function test_boost_is_recorded(): void
    {
        // Act
        $field = Field::text()->boost(2.5);

        // Assert
        self::assertSame(['type' => 'text', 'boost' => 2.5], $field->toArray());
    }

    public function test_ignore_above_is_recorded(): void
    {
        // Act
        $field = Field::keyword()->ignoreAbove(256);

        // Assert
        self::assertSame(['type' => 'keyword', 'ignore_above' => 256], $field->toArray());
    }

    public function test_null_value_is_recorded(): void
    {
        // Act
        $field = Field::keyword()->nullValue('N/A');

        // Assert
        self::assertSame(['type' => 'keyword', 'null_value' => 'N/A'], $field->toArray());
    }

    public function test_meta_is_recorded(): void
    {
        // Act
        $field = Field::text()->meta(['unit' => 'ms']);

        // Assert
        self::assertSame(['type' => 'text', 'meta' => ['unit' => 'ms']], $field->toArray());
    }

    public function test_dynamic_is_recorded(): void
    {
        // Act
        $field = Field::object()->dynamic('strict');

        // Assert
        self::assertSame(['type' => 'object', 'dynamic' => 'strict'], $field->toArray());
    }

    public function test_enabled_flag_is_recorded(): void
    {
        // Act
        $field = Field::object()->enabled(false);

        // Assert
        self::assertSame(['type' => 'object', 'enabled' => false], $field->toArray());
    }

    public function test_option_sets_an_arbitrary_key(): void
    {
        // Act
        $field = Field::integer()->option('coerce', false);

        // Assert
        self::assertSame(['type' => 'integer', 'coerce' => false], $field->toArray());
    }

    public function test_field_adds_a_multi_field_and_serialises_it(): void
    {
        // Act
        $field = Field::text()->field('keyword', Field::keyword());

        // Assert
        self::assertSame(
            ['type' => 'text', 'fields' => ['keyword' => ['type' => 'keyword']]],
            $field->toArray(),
        );
    }

    public function test_property_adds_a_nested_property_and_serialises_it(): void
    {
        // Act
        $field = Field::nested()->property('body', Field::text());

        // Assert
        self::assertSame(
            ['type' => 'nested', 'properties' => ['body' => ['type' => 'text']]],
            $field->toArray(),
        );
    }

    public function test_get_options_returns_every_configured_option(): void
    {
        // Act
        $field = Field::text()->analyzer('standard')->index(true);

        // Assert
        self::assertSame(['analyzer' => 'standard', 'index' => true], $field->getOptions());
    }

    public function test_fluent_configuration_returns_the_same_instance(): void
    {
        // Arrange
        $field = Field::text();

        // Act / Assert
        self::assertSame($field, $field->analyzer('standard'));
        self::assertSame($field, $field->index(false));
        self::assertSame($field, $field->option('x', 1));
    }
}
