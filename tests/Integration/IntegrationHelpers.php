<?php

declare(strict_types=1);

namespace EsOrm\Tests\Integration;

use EsOrm\Index\IndexManager;
use EsOrm\Tests\Fixtures\IntegrationUser;
use stdClass;

/**
 * Shared helpers for the integration/feature suites.
 *
 * These helpers only talk to the real Elasticsearch node through the connection
 * exposed by {@see IntegrationTestCase}; they never mock anything.
 */
trait IntegrationHelpers
{
    /**
     * Create a fresh, throw-away index using {@see IntegrationUser}'s mapping and
     * point the model at it.
     */
    protected function createUserIndex(string $hint = 'users'): string
    {
        $index = $this->uniqueIndex($hint);
        IntegrationUser::useIndex($index);

        IndexManager::on($this->manager)->createIndex(
            $index,
            (new IntegrationUser())->mappingDefinition(),
            ['number_of_replicas' => 0],
        );

        return $index;
    }

    /**
     * Index the given documents through the model and refresh so they become
     * searchable.
     *
     * @param list<array<string, mixed>> $documents
     */
    protected function seedUsers(string $index, array $documents): void
    {
        foreach ($documents as $document) {
            IntegrationUser::create($document);
        }

        $this->es('refresh', ['index' => $index]);
    }

    /**
     * Run a `match_all` search straight against Elasticsearch through the ORM
     * connection (used to prove alias routing).
     *
     * @return array<string, mixed>
     */
    protected function rawMatchAll(string $index, int $size = 10): array
    {
        return $this->es('search', [
            'index' => $index,
            'body' => [
                'size' => $size,
                'query' => ['match_all' => new stdClass()],
            ],
        ]);
    }
}
