<?php

declare(strict_types=1);

namespace EsOrm\Tests\Fixtures;

use EsOrm\Model\Model;

/**
 * A rich model used by the integration/feature suites that talk to a real
 * Elasticsearch node.
 *
 * Unlike the other fixtures it does not hard-code its index: the concrete index
 * is injected at runtime through {@see IntegrationUser::useIndex()} so every test
 * can create its own uniquely named, throw-away index without touching the
 * shared fixtures.
 */
final class IntegrationUser extends Model
{
    protected string $index = 'integration_users';

    /**
     * The index the model currently points at (null falls back to `$index`).
     */
    private static ?string $overrideIndex = null;

    /**
     * Point every query/write of this model at the given index.
     */
    public static function useIndex(string $index): void
    {
        self::$overrideIndex = $index;
    }

    public function getIndex(): string
    {
        return self::$overrideIndex ?? $this->index;
    }

    /**
     * @var list<string>
     */
    protected array $fillable = [
        'id',
        'name',
        'email',
        'age',
        'status',
        'city',
        'tags',
        'profile',
        'created_at',
    ];

    /**
     * @var array<string, string>
     */
    protected array $casts = [
        'age' => 'integer',
        'created_at' => 'datetime',
        'tags' => 'array',
    ];

    /**
     * Declared `public` so the base {@see Model::mapping()} contract is preserved.
     *
     * @return array<string, mixed>
     */
    public function mapping(): array
    {
        return [
            'properties' => [
                'name' => [
                    'type' => 'text',
                    'fields' => ['keyword' => ['type' => 'keyword']],
                ],
                'email' => ['type' => 'keyword'],
                'status' => ['type' => 'keyword'],
                'city' => ['type' => 'keyword'],
                'age' => ['type' => 'integer'],
                'tags' => ['type' => 'keyword'],
                'created_at' => ['type' => 'date'],
                'profile' => [
                    'type' => 'nested',
                    'properties' => [
                        'city' => ['type' => 'keyword'],
                        'skill' => ['type' => 'keyword'],
                    ],
                ],
            ],
        ];
    }
}
