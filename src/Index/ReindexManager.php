<?php

declare(strict_types=1);

namespace EsOrm\Index;

use EsOrm\Contracts\ConnectionInterface;
use EsOrm\Contracts\ConnectionManagerInterface;

/**
 * Copies documents from one index into another using the `_reindex` API.
 *
 * Reindexing is the core building block of zero-downtime index upgrades: build
 * `articles_v2`, reindex `articles_v1` into it, then atomically switch the
 * `articles` alias (see {@see AliasManager::switchAlias()}).
 *
 * ```php
 * $reindex = ReindexManager::on($connectionManager);
 *
 * $reindex->reindexTo('articles_v1', 'articles_v2', [
 *     'query'      => ['term' => ['status' => 'published']],
 *     'batch_size' => 1000,
 *     'script'     => ['source' => 'ctx._source.migrated = true'],
 * ]);
 * ```
 */
final class ReindexManager
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
     * Run a raw `_reindex` request.
     *
     * @param array<string, mixed> $params Raw Elasticsearch `_reindex` parameters (e.g. `['body' => [...]]`).
     * @return array<string, mixed>
     */
    public function reindex(array $params): array
    {
        return $this->connection->request('reindex', $params);
    }

    /**
     * Copy `$source` into `$destination`.
     *
     * Supported options:
     *  - `query`         (array)  only reindex matching documents
     *  - `batch_size`    (int)    documents per scroll batch (`source.size`)
     *  - `max_docs`      (int)    stop after this many documents
     *  - `script`        (array)  transform documents while copying
     *  - `conflicts`     (string) `proceed` (default) or `abort`
     *  - `refresh`       (bool)   refresh the destination afterwards
     *  - `wait_for_completion` (bool) run synchronously (default) or as a task
     *  - `slices`        (int|string|array) parallelise the copy
     *  - `_source`       (array)  source filtering (`['includes' => [...]]`)
     *
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    public function reindexTo(string $source, string $destination, array $options = []): array
    {
        return $this->reindex(self::buildParams($source, $destination, $options));
    }

    /**
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    private static function buildParams(string $source, string $destination, array $options): array
    {
        $sourceParams = ['index' => $source];

        if (isset($options['query'])) {
            $sourceParams['query'] = $options['query'];
        }

        $size = $options['batch_size'] ?? $options['size'] ?? null;

        if (is_numeric($size)) {
            $sourceParams['size'] = (int) $size;
        }

        if (isset($options['_source']) && is_array($options['_source'])) {
            $sourceParams['_source'] = $options['_source'];
        }

        $body = [
            'source' => $sourceParams,
            'dest' => ['index' => $destination],
        ];

        if (isset($options['script'])) {
            $body['script'] = $options['script'];
        }

        if (isset($options['conflicts'])) {
            $body['conflicts'] = $options['conflicts'];
        }

        if (is_numeric($options['max_docs'] ?? null)) {
            $body['max_docs'] = (int) $options['max_docs'];
        }

        $params = ['body' => $body];

        if (isset($options['slices'])) {
            $params['slices'] = $options['slices'];
        }

        if (isset($options['refresh'])) {
            $params['refresh'] = (bool) $options['refresh'];
        }

        if (isset($options['wait_for_completion'])) {
            $params['wait_for_completion'] = (bool) $options['wait_for_completion'];
        }

        return $params;
    }
}
