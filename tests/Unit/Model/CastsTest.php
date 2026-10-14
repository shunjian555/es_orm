<?php

declare(strict_types=1);

namespace EsOrm\Tests\Unit\Model;

use DateTimeImmutable;
use EsOrm\Exceptions\MappingException;
use EsOrm\Model\Casts\ArrayCast;
use EsOrm\Model\Casts\BooleanCast;
use EsOrm\Model\Casts\CastFactory;
use EsOrm\Model\Casts\CastInterface;
use EsOrm\Model\Casts\DateCast;
use EsOrm\Model\Casts\DatetimeCast;
use EsOrm\Model\Casts\FloatCast;
use EsOrm\Model\Casts\IntegerCast;
use EsOrm\Model\Casts\JsonCast;
use EsOrm\Model\Casts\ObjectCast;
use EsOrm\Model\Casts\StringCast;
use EsOrm\Tests\Fixtures\User;
use EsOrm\Tests\TestCase;

final class CastsTest extends TestCase
{
    /* ----------------------------------------------------------------
     |  Factory
     * ---------------------------------------------------------------- */

    public function test_it_reports_which_cast_types_are_supported(): void
    {
        // Act & Assert
        self::assertTrue(CastFactory::supports('integer'));
        self::assertTrue(CastFactory::supports('INTEGER'));
        self::assertTrue(CastFactory::supports('bool'));
        self::assertFalse(CastFactory::supports('unknown'));
    }

    public function test_it_resolves_a_cast_type_into_its_implementation(): void
    {
        // Act & Assert
        self::assertInstanceOf(IntegerCast::class, CastFactory::make('int'));
        self::assertInstanceOf(FloatCast::class, CastFactory::make('double'));
        self::assertInstanceOf(BooleanCast::class, CastFactory::make('boolean'));
        self::assertInstanceOf(StringCast::class, CastFactory::make('string'));
        self::assertInstanceOf(ArrayCast::class, CastFactory::make('array'));
        self::assertInstanceOf(JsonCast::class, CastFactory::make('json'));
        self::assertInstanceOf(ObjectCast::class, CastFactory::make('map'));
        self::assertInstanceOf(DatetimeCast::class, CastFactory::make('datetime'));
        self::assertInstanceOf(DateCast::class, CastFactory::make('date'));
    }

    public function test_it_throws_a_mapping_exception_for_an_unknown_cast(): void
    {
        // Arrange
        $this->expectException(MappingException::class);
        $this->expectExceptionMessage('Unsupported cast type [nope].');

        // Act
        CastFactory::make('nope');
    }

    public function test_it_returns_the_given_cast_instance_unchanged(): void
    {
        // Arrange
        $cast = new IntegerCast();

        // Act & Assert
        self::assertSame($cast, CastFactory::make($cast));
    }

    /* ----------------------------------------------------------------
     |  Scalar casts
     * ---------------------------------------------------------------- */

    public function test_it_casts_integers_both_ways(): void
    {
        // Arrange
        $cast = new IntegerCast();

        // Act & Assert
        self::assertSame(42, $cast->get('42'));
        self::assertSame(42, $cast->set('42'));
        self::assertNull($cast->get(null));
        self::assertNull($cast->set(null));
        self::assertSame(1, $cast->set(true));
    }

    public function test_it_casts_floats_both_ways(): void
    {
        // Arrange
        $cast = new FloatCast();

        // Act & Assert
        self::assertSame(9.5, $cast->get('9.5'));
        self::assertSame(9.0, $cast->set(9));
        self::assertNull($cast->get(null));
        self::assertNull($cast->set(null));
    }

    public function test_it_casts_booleans_both_ways(): void
    {
        // Arrange
        $cast = new BooleanCast();

        // Act & Assert
        self::assertTrue($cast->get('true'));
        self::assertTrue($cast->get('yes'));
        self::assertTrue($cast->get(1));
        self::assertFalse($cast->get('0'));
        self::assertTrue($cast->set(1));
        self::assertFalse($cast->set(0));
        self::assertNull($cast->get(null));
        self::assertNull($cast->set(null));
    }

    public function test_it_casts_strings_both_ways(): void
    {
        // Arrange
        $cast = new StringCast();

        // Act & Assert
        self::assertSame('123', $cast->get(123));
        self::assertSame('123', $cast->set(123));
        self::assertNull($cast->get(null));
        self::assertNull($cast->set(null));
    }

    /* ----------------------------------------------------------------
     |  Structured casts
     * ---------------------------------------------------------------- */

    public function test_it_casts_arrays_both_ways(): void
    {
        // Arrange
        $cast = new ArrayCast();

        // Act & Assert
        self::assertSame(['a', 'b'], $cast->get('["a","b"]'));
        self::assertSame(['a', 'b'], $cast->get(['a', 'b']));
        self::assertSame(['plain'], $cast->get('plain'));
        self::assertSame([5], $cast->get(5));
        self::assertSame(['x'], $cast->set('x'));
        self::assertNull($cast->get(null));
        self::assertNull($cast->set(null));
    }

    public function test_it_casts_json_both_ways(): void
    {
        // Arrange
        $cast = new JsonCast();

        // Act & Assert
        self::assertSame(['k' => 'v'], $cast->get('{"k":"v"}'));
        self::assertSame(['k' => 'v'], $cast->set('{"k":"v"}'));
        self::assertSame(['a' => 1], $cast->set(['a' => 1]));
        self::assertSame('plain', $cast->set('plain'));
        self::assertNull($cast->get('not json'));
        self::assertNull($cast->get(null));
        self::assertNull($cast->set(null));
    }

    public function test_it_casts_objects_both_ways(): void
    {
        // Arrange
        $cast = new ObjectCast();

        // Act & Assert
        self::assertSame(1, $cast->get('{"a":1}')->a);
        self::assertSame(1, $cast->get(['a' => 1])->a);
        self::assertSame(5, $cast->get(5)->value);
        self::assertSame(['a' => 1], $cast->set((object) ['a' => 1]));
        self::assertSame(['a' => 1], $cast->set(['a' => 1]));
        self::assertSame(['a' => 1], $cast->set('{"a":1}'));
        self::assertNull($cast->get(null));
        self::assertNull($cast->set(null));
    }

    /* ----------------------------------------------------------------
     |  Date casts
     * ---------------------------------------------------------------- */

    public function test_it_casts_datetimes_both_ways(): void
    {
        // Arrange
        $cast = new DatetimeCast();
        $moment = new DateTimeImmutable('2024-01-02T03:04:05+00:00');

        // Act & Assert
        self::assertEquals($moment, $cast->get('2024-01-02T03:04:05+00:00'));
        self::assertSame('2024-01-02T03:04:05+00:00', $cast->set($moment));
        self::assertNull($cast->get(null));
        self::assertNull($cast->set(null));
    }

    public function test_it_casts_dates_both_ways(): void
    {
        // Arrange
        $cast = new DateCast();
        $day = new DateTimeImmutable('2024-01-02');

        // Act & Assert
        self::assertEquals($day, $cast->get('2024-01-02'));
        self::assertSame('2024-01-02', $cast->set($day));
        self::assertNull($cast->get(null));
        self::assertNull($cast->set(null));
    }

    /* ----------------------------------------------------------------
     |  Model round-trips (ES value -> attribute -> PHP type)
     * ---------------------------------------------------------------- */

    public function test_it_hydrates_every_cast_on_read_through_a_model(): void
    {
        // Arrange
        $model = (new User())->newInstance([
            'age' => '42',
            'price' => '9.5',
            'active' => 'true',
            'metadata' => '["a","b"]',
            'options' => '{"k":"v"}',
            'profile' => '{"p":1}',
            'created_at' => '2024-01-02T03:04:05+00:00',
            'birthday' => '2024-01-02',
        ]);

        // Act & Assert
        self::assertSame(42, $model->getAttribute('age'));
        self::assertSame(9.5, $model->getAttribute('price'));
        self::assertTrue($model->getAttribute('active'));
        self::assertSame(['a', 'b'], $model->getAttribute('metadata'));
        self::assertSame(['k' => 'v'], $model->getAttribute('options'));
        self::assertEquals((object) ['p' => 1], $model->getAttribute('profile'));
        self::assertEquals(new DateTimeImmutable('2024-01-02T03:04:05+00:00'), $model->getAttribute('created_at'));
        self::assertEquals(new DateTimeImmutable('2024-01-02'), $model->getAttribute('birthday'));
    }

    public function test_it_prepares_every_cast_for_storage_on_write_through_a_model(): void
    {
        // Arrange
        $model = new User();

        // Act
        $model->age = '42';
        $model->price = '9.5';
        $model->active = 1;
        $model->metadata = 'x';
        $model->created_at = new DateTimeImmutable('2024-01-02T03:04:05+00:00');

        // Assert
        self::assertSame([
            'age' => 42,
            'price' => 9.5,
            'active' => true,
            'metadata' => ['x'],
            'created_at' => '2024-01-02T03:04:05+00:00',
        ], $model->getAttributes());
    }

    public function test_every_cast_implements_the_cast_interface(): void
    {
        // Act & Assert
        self::assertInstanceOf(CastInterface::class, new IntegerCast());
        self::assertInstanceOf(CastInterface::class, new FloatCast());
        self::assertInstanceOf(CastInterface::class, new BooleanCast());
        self::assertInstanceOf(CastInterface::class, new StringCast());
        self::assertInstanceOf(CastInterface::class, new ArrayCast());
        self::assertInstanceOf(CastInterface::class, new JsonCast());
        self::assertInstanceOf(CastInterface::class, new ObjectCast());
        self::assertInstanceOf(CastInterface::class, new DatetimeCast());
        self::assertInstanceOf(CastInterface::class, new DateCast());
    }
}
