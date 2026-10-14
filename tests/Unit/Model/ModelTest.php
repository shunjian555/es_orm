<?php

declare(strict_types=1);

namespace EsOrm\Tests\Unit\Model;

use ArrayAccess;
use EsOrm\Contracts\ModelInterface;
use EsOrm\Exceptions\InvalidQueryException;
use EsOrm\Exceptions\ModelNotFoundException;
use EsOrm\Model\ModelCollection;
use EsOrm\Query\Builder;
use EsOrm\Tests\Fixtures\Author;
use EsOrm\Tests\Fixtures\DefaultIndexModel;
use EsOrm\Tests\Fixtures\HiddenModel;
use EsOrm\Tests\Fixtures\UnguardedModel;
use EsOrm\Tests\Fixtures\User;
use EsOrm\Tests\Fixtures\VisibleModel;
use EsOrm\Tests\TestCase;

final class ModelTest extends TestCase
{
    /* ----------------------------------------------------------------
     |  Identity / index resolution
     * ---------------------------------------------------------------- */

    public function test_it_is_a_model_and_array_access_facade(): void
    {
        // Arrange
        $model = new User();

        // Act & Assert
        self::assertInstanceOf(ModelInterface::class, $model);
        self::assertInstanceOf(ArrayAccess::class, $model);
    }

    public function test_it_uses_the_explicit_index(): void
    {
        // Arrange
        $model = new User();

        // Act & Assert
        self::assertSame('users', $model->getIndex());
    }

    public function test_it_derives_the_default_index_from_the_class_name_in_snake_case(): void
    {
        // Arrange
        $model = new DefaultIndexModel();

        // Act & Assert
        self::assertSame('default_index_models', $model->getIndex());
    }

    public function test_it_exposes_the_primary_key_name(): void
    {
        // Arrange
        $model = new User();

        // Act & Assert
        self::assertSame('id', $model->getKeyName());
    }

    public function test_it_reports_not_existing_by_default(): void
    {
        // Arrange
        $model = new User();

        // Act & Assert
        self::assertFalse($model->exists());
    }

    public function test_it_returns_null_key_for_a_new_model(): void
    {
        // Arrange
        $model = new User();

        // Act & Assert
        self::assertNull($model->getKey());
    }

    public function test_it_reads_the_key_from_the_attributes(): void
    {
        // Arrange
        $model = (new User())->newInstance(['id' => '42', 'name' => 'Tom'], true);

        // Act & Assert
        self::assertSame('42', $model->getKey());
    }

    public function test_it_injects_the_document_id_into_the_key_when_the_source_lacks_it(): void
    {
        // Arrange
        $model = (new User())->newFromHit(['_id' => '99', '_index' => 'users', '_source' => ['name' => 'Tom']]);

        // Act & Assert
        self::assertSame('99', $model->getKey());
        self::assertTrue($model->exists());
    }

    /* ----------------------------------------------------------------
     |  Attributes
     * ---------------------------------------------------------------- */

    public function test_it_sets_and_gets_attributes_through_magic_accessors(): void
    {
        // Arrange
        $model = new User();

        // Act
        $model->name = 'Tom';

        // Assert
        self::assertSame('Tom', $model->name);
        self::assertSame('Tom', $model->getAttribute('name'));
    }

    public function test_it_reads_and_writes_attributes_through_array_access(): void
    {
        // Arrange
        $model = new User();

        // Act
        $model['age'] = '30';

        // Assert
        self::assertSame(30, $model['age']);
        self::assertSame(30, $model->getAttribute('age'));
    }

    public function test_it_reports_attribute_existence_through_isset(): void
    {
        // Arrange
        $model = new User();
        $model->name = 'Tom';

        // Act & Assert
        self::assertTrue(isset($model->name));
        self::assertTrue(isset($model['name']));
        self::assertFalse(isset($model->nickname));
        self::assertTrue(isset($model->highlights));
    }

    public function test_it_unsets_attributes_through_unset(): void
    {
        // Arrange
        $model = new User();
        $model->name = 'Tom';

        // Act
        unset($model['name']);

        // Assert
        self::assertFalse(isset($model->name));
    }

    public function test_it_fills_only_fillable_attributes(): void
    {
        // Arrange
        $model = new User();

        // Act
        $model->fill(['name' => 'Tom', 'unknown' => 'x']);

        // Assert
        self::assertSame('Tom', $model->getAttribute('name'));
        self::assertNull($model->getAttribute('unknown'));
    }

    public function test_it_force_fills_guarded_attributes(): void
    {
        // Arrange
        $model = new User();

        // Act
        $model->forceFill(['unknown' => 'x']);

        // Assert
        self::assertSame('x', $model->getAttribute('unknown'));
    }

    public function test_it_marks_every_attribute_guarded_by_default(): void
    {
        // Arrange
        $model = new DefaultIndexModel();

        // Act & Assert
        self::assertTrue($model->isGuarded('anything'));
        self::assertFalse($model->isFillable('anything'));
    }

    public function test_it_treats_every_attribute_as_fillable_when_unguarded(): void
    {
        // Arrange
        $model = new UnguardedModel();

        // Act
        $model->fill(['name' => 'Tom']);

        // Assert
        self::assertTrue($model->isFillable('name'));
        self::assertFalse($model->isFillable('_meta'));
        self::assertSame('Tom', $model->getAttribute('name'));
    }

    /* ----------------------------------------------------------------
     |  Instances & queries
     * ---------------------------------------------------------------- */

    public function test_it_creates_a_new_instance_with_synced_attributes(): void
    {
        // Arrange
        $model = (new User())->newInstance(['id' => '1', 'name' => 'Tom'], true);

        // Act & Assert
        self::assertSame(['id' => '1', 'name' => 'Tom'], $model->getAttributes());
        self::assertTrue($model->exists());
        self::assertFalse((new User())->newInstance(['id' => '2'])->exists());
    }

    public function test_it_builds_a_builder_through_new_query(): void
    {
        // Arrange
        $model = new User();

        // Act
        $builder = $model->newQuery();

        // Assert
        self::assertInstanceOf(Builder::class, $builder);
        self::assertSame('users', $builder->getIndex());
        self::assertSame($model->getIndex(), $builder->getModel()->getIndex());
    }

    public function test_it_builds_a_builder_through_the_static_query_helper(): void
    {
        // Arrange
        $builder = User::query();

        // Act & Assert
        self::assertInstanceOf(Builder::class, $builder);
        self::assertSame('users', $builder->getIndex());
    }

    /* ----------------------------------------------------------------
     |  Reads
     * ---------------------------------------------------------------- */

    public function test_it_finds_a_model_by_id(): void
    {
        // Arrange
        $this->client->setResponse('search', [
            'hits' => ['total' => ['value' => 1], 'hits' => [['_id' => '7', '_index' => 'users', '_source' => ['name' => 'Tom']]]],
        ]);

        // Act
        $model = User::find('7');

        // Assert
        self::assertInstanceOf(User::class, $model);
        self::assertSame('7', $model->getKey());
        self::assertSame('Tom', $model->getAttribute('name'));
        self::assertTrue($model->exists());
        self::assertSame(['term' => ['id' => '7']], $this->client->lastParams('search')['body']['query']['bool']['must'][0]);
    }

    public function test_it_returns_null_when_find_has_no_result(): void
    {
        // Arrange — the fake client defaults to an empty result set.

        // Act
        $model = User::find('404');

        // Assert
        self::assertNull($model);
        self::assertSame(1, $this->client->callCount('search'));
    }

    public function test_it_returns_null_when_first_has_no_result(): void
    {
        // Act
        $model = User::first();

        // Assert
        self::assertNull($model);
    }

    public function test_it_throws_when_find_or_fail_has_no_result(): void
    {
        // Arrange
        $this->expectException(ModelNotFoundException::class);

        // Act
        User::findOrFail('404');
    }

    public function test_it_reports_the_model_and_id_in_the_not_found_exception(): void
    {
        // Arrange & Act
        try {
            User::findOrFail('404');
            self::fail('Expected a ModelNotFoundException to be thrown.');
        } catch (ModelNotFoundException $exception) {
            // Assert
            self::assertSame(User::class, $exception->getModel());
            self::assertSame('404', $exception->getId());
        }
    }

    public function test_it_returns_an_existing_model_from_find_or_fail(): void
    {
        // Arrange
        $this->client->setResponse('search', [
            'hits' => ['total' => ['value' => 1], 'hits' => [['_id' => '1', '_source' => ['id' => '1', 'name' => 'Tom']]]],
        ]);

        // Act
        $model = User::findOrFail('1');

        // Assert
        self::assertInstanceOf(User::class, $model);
        self::assertSame('Tom', $model->getAttribute('name'));
    }

    public function test_it_throws_when_first_or_fail_has_no_result(): void
    {
        // Arrange
        $this->expectException(ModelNotFoundException::class);

        // Act
        User::firstOrFail();
    }

    /* ----------------------------------------------------------------
     |  Serialization
     * ---------------------------------------------------------------- */

    public function test_it_serializes_attributes_applying_the_casts(): void
    {
        // Arrange
        $model = (new User())->newInstance([
            'id' => '1',
            'name' => 'Tom',
            'age' => '30',
            'active' => 'true',
            'price' => '9.5',
        ]);

        // Act
        $array = $model->toArray();

        // Assert
        self::assertSame(['id' => '1', 'name' => 'Tom', 'age' => 30, 'active' => true, 'price' => 9.5], $array);
    }

    public function test_it_serializes_to_json(): void
    {
        // Arrange
        $model = (new User())->newInstance(['id' => '1', 'name' => 'Tom', 'age' => '30']);

        // Act
        $json = $model->toJson();

        // Assert
        self::assertJsonStringEqualsJsonString('{"id":"1","name":"Tom","age":30}', $json);
    }

    public function test_it_hides_hidden_attributes_from_array_output(): void
    {
        // Arrange
        $model = (new HiddenModel())->newInstance(['id' => '1', 'name' => 'Tom', 'secret' => 'x']);

        // Act
        $array = $model->toArray();

        // Assert
        self::assertSame(['id' => '1', 'name' => 'Tom'], $array);
        self::assertArrayNotHasKey('secret', $array);
    }

    public function test_it_only_exposes_visible_attributes_in_array_output(): void
    {
        // Arrange
        $model = (new VisibleModel())->newInstance(['id' => '1', 'name' => 'Tom', 'secret' => 'x']);

        // Act
        $array = $model->toArray();

        // Assert
        self::assertSame(['name' => 'Tom'], $array);
    }

    public function test_it_includes_loaded_relations_in_array_output(): void
    {
        // Arrange
        $parent = (new Author())->newInstance(['id' => '1', 'name' => 'Bob'], true);
        $child = (new User())->newInstance(['id' => '2', 'name' => 'Tom'], true);
        $parent->setRelation('profile', $child);
        $parent->setRelation('articles', new ModelCollection([$child]));

        // Act
        $array = $parent->toArray();

        // Assert
        self::assertSame([
            'id' => '1',
            'name' => 'Bob',
            'profile' => ['id' => '2', 'name' => 'Tom'],
            'articles' => [['id' => '2', 'name' => 'Tom']],
        ], $array);
    }

    /* ----------------------------------------------------------------
     |  CRUD requests
     * ---------------------------------------------------------------- */

    public function test_it_indexes_a_new_document_with_exact_request_params(): void
    {
        // Arrange
        $this->client->setResponse('index', ['_index' => 'users', '_id' => '100', 'result' => 'created']);

        // Act
        User::create(['name' => 'Tom']);

        // Assert
        self::assertSame(['index' => 'users', 'body' => ['name' => 'Tom']], $this->client->lastParams('index'));
    }

    public function test_it_indexes_a_new_document_with_an_explicit_id(): void
    {
        // Arrange
        $this->client->setResponse('index', ['_index' => 'users', '_id' => '42']);

        // Act
        User::create(['id' => '42', 'name' => 'Tom']);

        // Assert
        self::assertSame(
            ['index' => 'users', 'body' => ['id' => '42', 'name' => 'Tom'], 'id' => '42'],
            $this->client->lastParams('index'),
        );
    }

    public function test_it_returns_true_from_save_and_marks_the_model_existing(): void
    {
        // Arrange
        $this->client->setResponse('index', ['_index' => 'users', '_id' => '1']);
        $model = new User();
        $model->fill(['name' => 'Tom']);

        // Act
        $saved = $model->save();

        // Assert
        self::assertTrue($saved);
        self::assertTrue($model->exists());
        self::assertSame('1', $model->getKey());
    }

    public function test_it_updates_an_existing_document_with_exact_request_params(): void
    {
        // Arrange
        $model = (new User())->newInstance(['id' => '1', 'name' => 'Tom'], true);

        // Act
        $updated = $model->update(['name' => 'Jerry']);

        // Assert
        self::assertTrue($updated);
        self::assertSame(
            ['index' => 'users', 'id' => '1', 'body' => ['doc' => ['id' => '1', 'name' => 'Jerry']]],
            $this->client->lastParams('update'),
        );
    }

    public function test_it_throws_when_updating_without_a_primary_key(): void
    {
        // Arrange
        $model = (new User())->newInstance([], true);
        $this->expectException(InvalidQueryException::class);

        // Act
        $model->update(['name' => 'Jerry']);
    }

    public function test_it_deletes_a_document_with_exact_request_params(): void
    {
        // Arrange
        $model = (new User())->newInstance(['id' => '1'], true);

        // Act
        $deleted = $model->delete();

        // Assert
        self::assertTrue($deleted);
        self::assertSame(['index' => 'users', 'id' => '1'], $this->client->lastParams('delete'));
        self::assertFalse($model->exists());
    }

    public function test_it_refreshes_from_the_get_response_merging_the_source(): void
    {
        // Arrange
        $this->client->setResponse('get', [
            'found' => true,
            '_index' => 'users',
            '_source' => ['name' => 'Fresh', 'age' => '40'],
        ]);
        $model = (new User())->newInstance(['id' => '1', 'name' => 'Stale'], true);

        // Act
        $model->refresh();

        // Assert
        self::assertSame(['index' => 'users', 'id' => '1'], $this->client->lastParams('get'));
        self::assertSame('Fresh', $model->getAttribute('name'));
        self::assertSame(40, $model->getAttribute('age'));
        self::assertSame('1', $model->getKey());
        self::assertTrue($model->exists());
    }

    public function test_it_does_not_request_get_when_refreshing_without_a_key(): void
    {
        // Arrange
        $model = new User();

        // Act
        $model->refresh();

        // Assert
        self::assertSame(0, $this->client->callCount('get'));
    }

    public function test_it_reads_the_connection_name(): void
    {
        // Arrange
        $model = new User();

        // Act & Assert
        self::assertSame('', $model->getConnectionName());
    }
}
