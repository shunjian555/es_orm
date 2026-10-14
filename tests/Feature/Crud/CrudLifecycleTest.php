<?php

declare(strict_types=1);

namespace EsOrm\Tests\Feature\Crud;

use EsOrm\Tests\Fixtures\IntegrationUser;
use EsOrm\Tests\Integration\IntegrationHelpers;
use EsOrm\Tests\Integration\IntegrationTestCase;

/**
 * The full create → read → update → delete lifecycle against a real node.
 */
final class CrudLifecycleTest extends IntegrationTestCase
{
    use IntegrationHelpers;

    public function test_the_full_crud_lifecycle(): void
    {
        // Arrange
        $index = $this->createUserIndex('feature_crud');

        // Act: CREATE.
        $user = IntegrationUser::create([
            'id' => '42',
            'name' => 'Grace Hopper',
            'email' => 'grace@example.com',
            'age' => 45,
            'status' => 'active',
        ]);
        $this->es('refresh', ['index' => $index]);

        // Assert: READ back.
        $read = IntegrationUser::findOrFail('42');
        self::assertSame('Grace Hopper', $read->name);
        self::assertSame('grace@example.com', $read->email);
        self::assertSame(45, $read->age);
        self::assertTrue($read->exists());

        // Act: UPDATE (via the model).
        $read->update(['status' => 'inactive', 'age' => 46]);
        $this->es('refresh', ['index' => $index]);

        // Assert: the update persisted.
        $updated = IntegrationUser::findOrFail('42');
        self::assertSame('inactive', $updated->status);
        self::assertSame(46, $updated->age);

        $inactive = IntegrationUser::query()->where('status', 'inactive')->count();
        self::assertSame(1, $inactive);

        // Act: DELETE.
        $deleted = $updated->delete();
        $this->es('refresh', ['index' => $index]);

        // Assert: gone from Elasticsearch.
        self::assertTrue($deleted);
        self::assertNull(IntegrationUser::find('42'));
        self::assertSame(0, IntegrationUser::query()->count());
    }
}
