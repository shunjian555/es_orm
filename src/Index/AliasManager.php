<?php

declare(strict_types=1);

namespace EsOrm\Index;

use EsOrm\Contracts\ConnectionInterface;
use EsOrm\Contracts\ConnectionManagerInterface;
use EsOrm\Exceptions\IndexException;

/**
 * Manages index aliases, including atomic alias switching.
 *
 * Aliases let a stable name (e.g. `articles`) point at a concrete index (e.g.
 * `articles_v1`). Rolling a new index into production is then a single atomic
 * `updateAliases` request, so readers never see a half switched alias.
 *
 * ```php
 * $aliases = AliasManager::on($connectionManager);
 *
 * $aliases->createAlias('articles_v1', 'articles');
 * $aliases->switchAlias('articles', 'articles_v2'); // -> articles_v2
 * ```
 */
final class AliasManager
{
    public function __construct(private readonly ConnectionInterface $connection)
    {
    }

    /**
     * Build a manager bound to a named connection.
     */
    public static function on(ConnectionManagerInterface $manager, ?string $connection = null): self
    {
        return new self($manager->connection($connection));
    }

    /**
     * Point an alias at an index.
     *
     * @param array<string, mixed> $options Extra `add` action options (e.g. `routing`, `filter`).
     */
    public function createAlias(string $index, string $alias, array $options = []): bool
    {
        $this->connection->request('updateAliases', [
            'body' => [
                'actions' => [
                    ['add' => array_replace(['index' => $index, 'alias' => $alias], $options)],
                ],
            ],
        ]);

        return true;
    }

    /**
     * Remove an alias from an index.
     */
    public function deleteAlias(string $index, string $alias): bool
    {
        $this->connection->request('updateAliases', [
            'body' => [
                'actions' => [
                    ['remove' => ['index' => $index, 'alias' => $alias]],
                ],
            ],
        ]);

        return true;
    }

    /**
     * Atomically move an alias to a new index.
     *
     * When `$from` is omitted the current indices of the alias are resolved and
     * removed first, so the alias always ends up pointing at exactly `$to`.
     *
     * @param string|list<string>|null $from The index(es) to detach, or null to auto-detect.
     */
    public function switchAlias(string $alias, string $to, string|array|null $from = null): bool
    {
        $actions = [];

        foreach ($from === null ? $this->indicesForAlias($alias) : (array) $from as $index) {
            if ($index !== $to) {
                $actions[] = ['remove' => ['index' => $index, 'alias' => $alias]];
            }
        }

        $actions[] = ['add' => ['index' => $to, 'alias' => $alias]];

        $this->connection->request('updateAliases', ['body' => ['actions' => $actions]]);

        return true;
    }

    /**
     * The aliases (and their indices) matching the given index or alias name.
     *
     * @return array<string, mixed>
     */
    public function getAlias(string $index): array
    {
        return $this->connection->request('getAlias', ['index' => $index]);
    }

    /**
     * Resolve the concrete indices currently backing an alias.
     *
     * @return list<string>
     */
    private function indicesForAlias(string $alias): array
    {
        try {
            $response = $this->connection->request('getAlias', ['name' => $alias]);
        } catch (IndexException) {
            return [];
        }

        $indices = [];

        foreach (array_keys($response) as $index) {
            if (is_string($index)) {
                $indices[] = $index;
            }
        }

        return $indices;
    }
}
