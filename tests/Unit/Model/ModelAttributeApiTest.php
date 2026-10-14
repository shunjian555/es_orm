<?php

declare(strict_types=1);

namespace EsOrm\Tests\Unit\Model;

use EsOrm\Tests\Fixtures\AccessorModel;
use EsOrm\Tests\TestCase;

/**
 * Covers the attribute/metadata public API of {@see \EsOrm\Model\Model} that is
 * not exercised by the generic model tests: mutator-aware setAttribute(), raw
 * attribute access, document metadata (_score, highlight) and ArrayAccess.
 */
final class ModelAttributeApiTest extends TestCase
{
    public function test_it_sets_an_attribute_through_the_mutator(): void
    {
        // Act
        $model = (new AccessorModel())->setAttribute('name', 'ALICE');

        // Assert: the setter lowercases, the getter uppercases again.
        self::assertSame('alice', $model->getAttributesForStorage()['name']);
        self::assertSame('ALICE', $model->getAttribute('name'));
        self::assertSame($model, $model->setAttribute('name', 'BOB'));
    }

    public function test_it_exposes_the_attributes_that_will_be_stored(): void
    {
        // Arrange
        $model = new AccessorModel();
        $model->setAttribute('name', 'ALICE');

        // Act
        $storage = $model->getAttributesForStorage();

        // Assert: the stored value is the mutated one, not the input.
        self::assertSame(['name' => 'alice'], $storage);
    }

    public function test_it_replaces_the_attributes_rawly(): void
    {
        // Act
        $model = (new AccessorModel())->setRawAttributes(['name' => 'alice', 'prefix' => 'dr']);

        // Assert: raw assignment bypasses the mutators, and the virtual
        // accessor composes `label` from the stored attributes.
        self::assertSame(['name' => 'alice', 'prefix' => 'dr'], $model->getAttributesForStorage());
        self::assertSame('dr:alice', $model->getAttribute('label'));
    }

    public function test_it_returns_the_document_score_from_the_hit_metadata(): void
    {
        // Act
        $model = (new AccessorModel())->newFromHit([
            '_id' => '1',
            '_score' => 1.75,
            '_source' => ['name' => 'alice'],
        ]);

        // Assert
        self::assertSame(1.75, $model->getScore());
    }

    public function test_it_normalises_an_integer_score_to_float(): void
    {
        // Act
        $model = (new AccessorModel())->newFromHit([
            '_id' => '1',
            '_score' => 1,
            '_source' => [],
        ]);

        // Assert
        self::assertSame(1.0, $model->getScore());
    }

    public function test_it_returns_a_null_score_when_the_hit_has_none(): void
    {
        // Act
        $model = (new AccessorModel())->newFromHit(['_id' => '1', '_source' => []]);

        // Assert
        self::assertNull($model->getScore());
    }

    public function test_it_exposes_the_highlight_fragments_of_a_hit(): void
    {
        // Act
        $model = (new AccessorModel())->newFromHit([
            '_id' => '1',
            '_source' => ['name' => 'alice'],
            'highlight' => ['name' => ['<em>alice</em>']],
        ]);

        // Assert
        self::assertSame(['name' => ['<em>alice</em>']], $model->highlights());
        self::assertSame(['<em>alice</em>'], $model->highlight('name'));
        self::assertNull($model->highlight('missing'));
    }

    public function test_it_supports_array_access_on_attributes(): void
    {
        // Arrange
        $model = new AccessorModel();

        // Act
        $model['name'] = 'ALICE';

        // Assert
        self::assertTrue(isset($model['name']));
        self::assertSame('ALICE', $model['name']);
        self::assertSame('alice', $model->getAttributesForStorage()['name']);

        unset($model['name']);
        self::assertFalse(isset($model['name']));
    }

    public function test_it_exposes_the_configured_connection_manager(): void
    {
        // Assert
        self::assertSame($this->manager, AccessorModel::connectionManager());
    }
}
