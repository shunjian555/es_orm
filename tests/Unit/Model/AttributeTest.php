<?php

declare(strict_types=1);

namespace EsOrm\Tests\Unit\Model;

use EsOrm\Model\Attribute;
use EsOrm\Tests\Fixtures\AccessorModel;
use EsOrm\Tests\TestCase;

final class AttributeTest extends TestCase
{
    public function test_it_reports_whether_a_getter_or_setter_is_defined(): void
    {
        // Arrange
        $both = Attribute::make(get: static fn (mixed $value): mixed => $value, set: static fn (mixed $value): mixed => $value);
        $none = Attribute::make();

        // Act & Assert
        self::assertTrue($both->hasGetter());
        self::assertTrue($both->hasSetter());
        self::assertFalse($none->hasGetter());
        self::assertFalse($none->hasSetter());
    }

    public function test_it_returns_the_raw_value_when_no_getter_is_defined(): void
    {
        // Arrange
        $attribute = Attribute::make();

        // Act & Assert
        self::assertSame('raw', $attribute->get('raw'));
    }

    public function test_it_returns_the_raw_value_when_no_setter_is_defined(): void
    {
        // Arrange
        $attribute = Attribute::make();

        // Act & Assert
        self::assertSame('raw', $attribute->set('raw'));
    }

    public function test_its_getter_receives_the_attribute_array(): void
    {
        // Arrange
        $attribute = Attribute::make(
            get: static fn (mixed $value, array $attributes): string => ($attributes['a'] ?? '') . (string) $value,
        );

        // Act
        $result = $attribute->get('b', ['a' => 'A']);

        // Assert
        self::assertSame('Ab', $result);
    }

    public function test_its_setter_receives_the_attribute_array(): void
    {
        // Arrange
        $attribute = Attribute::make(
            set: static fn (mixed $value, array $attributes): string => ($attributes['a'] ?? '') . (string) $value,
        );

        // Act
        $result = $attribute->set('b', ['a' => 'A']);

        // Assert
        self::assertSame('Ab', $result);
    }

    public function test_it_transforms_values_on_read_and_write_through_a_model(): void
    {
        // Arrange
        $model = new AccessorModel();

        // Act
        $model->name = 'Tom';

        // Assert
        self::assertSame('tom', $model->getAttributes()['name']);
        self::assertSame('TOM', $model->getAttribute('name'));
    }

    public function test_its_getter_can_build_a_virtual_attribute_from_other_attributes(): void
    {
        // Arrange
        $model = new AccessorModel();
        $model->forceFill(['prefix' => 'u', 'name' => 'Tom']);

        // Act
        $label = $model->getAttribute('label');

        // Assert
        self::assertSame('u:tom', $label);
    }

    public function test_it_ignores_a_method_that_does_not_declare_the_attribute_return_type(): void
    {
        // Arrange
        $model = new AccessorModel();

        // Act
        $model->nickname = 'MiXeD';

        // Assert
        self::assertSame('MiXeD', $model->getAttribute('nickname'));
        self::assertSame('MiXeD', $model->getAttributes()['nickname']);
    }
}
