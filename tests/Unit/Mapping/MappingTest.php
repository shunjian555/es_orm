<?php

declare(strict_types=1);

namespace EsOrm\Tests\Unit\Mapping;

use EsOrm\Contracts\MappingInterface;
use EsOrm\Mapping\Field;
use EsOrm\Mapping\Mapping;
use EsOrm\Tests\Fixtures\Post;
use EsOrm\Tests\TestCase;

final class MappingTest extends TestCase
{
    public function test_it_implements_the_mapping_contract(): void
    {
        // Arrange / Act
        $mapping = Mapping::create('articles');

        // Assert
        self::assertInstanceOf(MappingInterface::class, $mapping);
        self::assertSame('articles', $mapping->getIndex());
    }

    public function test_create_without_fields_has_no_properties(): void
    {
        // Act
        $mapping = Mapping::create('articles');

        // Assert
        self::assertFalse($mapping->hasProperties());
        self::assertSame(['properties' => []], $mapping->toArray());
    }

    public function test_it_builds_the_exact_properties_structure(): void
    {
        // Act
        $mapping = Mapping::create('articles')
            ->text('title')
            ->keyword('status')
            ->date('created_at')
            ->double('price');

        // Assert
        self::assertSame([
            'properties' => [
                'title' => ['type' => 'text'],
                'status' => ['type' => 'keyword'],
                'created_at' => ['type' => 'date'],
                'price' => ['type' => 'double'],
            ],
        ], $mapping->toArray());
    }

    public function test_it_serialises_the_geo_point_type(): void
    {
        // Act
        $mapping = Mapping::create('places')->geoPoint('location');

        // Assert
        self::assertSame(
            ['properties' => ['location' => ['type' => 'geo_point']]],
            $mapping->toArray(),
        );
    }

    public function test_field_of_builds_an_arbitrary_typed_field(): void
    {
        // Act
        $mapping = Mapping::create('articles')->fieldOf('rank_feature', 'rank');

        // Assert
        self::assertSame(['type' => 'rank_feature'], $mapping->properties()['rank']->toArray());
    }

    public function test_field_accepts_a_field_value_object(): void
    {
        // Act
        $mapping = Mapping::create('articles')->field('title', Field::text()->analyzer('standard'));

        // Assert
        self::assertSame(
            ['properties' => ['title' => ['type' => 'text', 'analyzer' => 'standard']]],
            $mapping->toArray(),
        );
    }

    public function test_raw_property_is_kept_untouched(): void
    {
        // Arrange
        $definition = ['type' => 'keyword', 'ignore_above' => 256];

        // Act
        $mapping = Mapping::create('articles')->rawProperty('tag', $definition);

        // Assert
        self::assertTrue($mapping->hasProperties());
        self::assertSame(['properties' => ['tag' => $definition]], $mapping->toArray());
    }

    public function test_from_array_accepts_a_full_body_with_settings(): void
    {
        // Act
        $mapping = Mapping::fromArray('articles', [
            'settings' => ['number_of_shards' => 3],
            'properties' => ['title' => ['type' => 'text']],
        ]);

        // Assert
        self::assertSame(['number_of_shards' => 3], $mapping->settings());
        self::assertSame(['properties' => ['title' => ['type' => 'text']]], $mapping->toArray());
    }

    public function test_from_array_accepts_a_mappings_body(): void
    {
        // Act
        $mapping = Mapping::fromArray('articles', [
            'properties' => ['status' => ['type' => 'keyword']],
        ]);

        // Assert
        self::assertSame([], $mapping->settings());
        self::assertSame(['properties' => ['status' => ['type' => 'keyword']]], $mapping->toArray());
    }

    public function test_from_array_accepts_a_bare_properties_array(): void
    {
        // Act
        $mapping = Mapping::fromArray('articles', ['title' => ['type' => 'text']]);

        // Assert
        self::assertSame(['properties' => ['title' => ['type' => 'text']]], $mapping->toArray());
    }

    public function test_from_array_with_only_settings_has_no_properties(): void
    {
        // Act
        $mapping = Mapping::fromArray('articles', ['settings' => ['number_of_shards' => 1]]);

        // Assert
        self::assertFalse($mapping->hasProperties());
        self::assertSame(['number_of_shards' => 1], $mapping->settings());
        self::assertSame(['properties' => []], $mapping->toArray());
    }

    public function test_from_array_skips_non_array_property_definitions(): void
    {
        // Act
        $mapping = Mapping::fromArray('articles', ['properties' => ['title' => 'text']]);

        // Assert
        self::assertSame(['properties' => []], $mapping->toArray());
    }

    public function test_from_array_on_an_empty_array_yields_no_properties(): void
    {
        // Act
        $mapping = Mapping::fromArray('articles', []);

        // Assert
        self::assertSame(['properties' => []], $mapping->toArray());
    }

    public function test_from_array_builds_the_mapping_of_a_model(): void
    {
        // Act
        $mapping = Post::query()->getModel()->mappingDefinition();

        // Assert
        self::assertSame('posts', $mapping->getIndex());
        self::assertSame([
            'properties' => [
                'title' => ['type' => 'text', 'fields' => ['keyword' => ['type' => 'keyword']]],
                'status' => ['type' => 'keyword'],
                'created_at' => ['type' => 'date'],
            ],
        ], $mapping->toArray());
    }

    public function test_with_settings_merges_into_the_settings(): void
    {
        // Act
        $mapping = Mapping::create('articles', ['number_of_shards' => 1])
            ->withSettings(['number_of_replicas' => 2]);

        // Assert
        self::assertSame(['number_of_shards' => 1, 'number_of_replicas' => 2], $mapping->settings());
    }

    public function test_with_settings_overrides_previously_declared_values(): void
    {
        // Act
        $mapping = Mapping::create('articles', ['number_of_shards' => 1])
            ->withSettings(['number_of_shards' => 5]);

        // Assert
        self::assertSame(['number_of_shards' => 5], $mapping->settings());
    }

    public function test_to_index_body_omits_settings_when_empty(): void
    {
        // Act
        $mapping = Mapping::create('articles')->keyword('status');

        // Assert
        self::assertSame([
            'mappings' => ['properties' => ['status' => ['type' => 'keyword']]],
        ], $mapping->toIndexBody());
    }

    public function test_to_index_body_includes_settings_first(): void
    {
        // Act
        $mapping = Mapping::create('articles', ['number_of_shards' => 1])->keyword('status');

        // Assert
        self::assertSame([
            'settings' => ['number_of_shards' => 1],
            'mappings' => ['properties' => ['status' => ['type' => 'keyword']]],
        ], $mapping->toIndexBody());
    }

    public function test_has_properties_is_true_once_a_field_is_added(): void
    {
        // Act
        $mapping = Mapping::create('articles')->text('title');

        // Assert
        self::assertTrue($mapping->hasProperties());
    }

    public function test_properties_exposes_the_field_value_objects(): void
    {
        // Act
        $mapping = Mapping::create('articles')->text('title');

        // Assert
        self::assertArrayHasKey('title', $mapping->properties());
        self::assertSame('text', $mapping->properties()['title']->getType());
    }

    public function test_fluent_builders_return_the_same_instance(): void
    {
        // Arrange
        $mapping = Mapping::create('articles');

        // Act / Assert
        self::assertSame($mapping, $mapping->text('title'));
        self::assertSame($mapping, $mapping->withSettings(['a' => 1]));
        self::assertSame($mapping, $mapping->rawProperty('x', ['type' => 'keyword']));
    }
}
