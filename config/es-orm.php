<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Default Connection
    |--------------------------------------------------------------------------
    |
    | The connection name that will be used by models that do not declare
    | their own `$connection` property.
    |
    */
    'default' => 'default',

    /*
    |--------------------------------------------------------------------------
    | Connections
    |--------------------------------------------------------------------------
    |
    | Every connection is built on top of the official Elasticsearch PHP client.
    | Supported keys: hosts, cloud_id, api_key, username/password (basic auth),
    | bearer_token, ssl_certificate / ssl_key / ssl_ca / ssl_verification,
    | timeout, connect_timeout, retry, logger.
    |
    */
    'connections' => [
        'default' => [
            'hosts' => [
                'http://localhost:9200',
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Retry
    |--------------------------------------------------------------------------
    |
    | Retries are only applied to *retryable* transport errors (connection
    | resets, 429 and 5xx responses).
    |
    */
    'retry' => [
        'max_retries' => 3,
        'retry_delay' => 100, // milliseconds
        'backoff' => 2,      // exponential backoff multiplier
        'max_delay' => 2000, // milliseconds
    ],

    /*
    |--------------------------------------------------------------------------
    | Logging / Query Debug
    |--------------------------------------------------------------------------
    |
    | When enabled, every executed request is recorded into the QueryLog so
    | that `$query->dump()` and `dumper` integrations can display the HTTP
    | method, endpoint, index, body, duration and errors.
    |
    */
    'logging' => [
        'enabled' => true,
        'max_entries' => 100,
    ],

    /*
    |--------------------------------------------------------------------------
    | Query Cache
    |--------------------------------------------------------------------------
    |
    | Query caching is disabled by default. The driver must implement
    | EsOrm\Contracts\CacheStoreInterface.
    |
    */
    'cache' => [
        'enabled' => false,
        'driver' => EsOrm\Support\Cache\ArrayCacheStore::class,
        'prefix' => 'es_orm:',
        'ttl' => 60,
    ],

    /*
    |--------------------------------------------------------------------------
    | Pagination
    |--------------------------------------------------------------------------
    */
    'pagination' => [
        'default_size' => 15,
        'max_size' => 10000,
        'track_total_hits' => true,
        'scroll_keep_alive' => '1m',
        'pit_keep_alive' => '1m',
    ],

    /*
    |--------------------------------------------------------------------------
    | Bulk
    |--------------------------------------------------------------------------
    */
    'bulk' => [
        'chunk_size' => 1000,
        'refresh' => false,
    ],

    /*
    |--------------------------------------------------------------------------
    | Index / Mapping
    |--------------------------------------------------------------------------
    */
    'index' => [
        'default_settings' => [
            'number_of_shards' => 1,
            'number_of_replicas' => 1,
        ],
    ],

    'mapping' => [
        'strict' => false,
    ],

    /*
    |--------------------------------------------------------------------------
    | Migrations
    |--------------------------------------------------------------------------
    |
    | The standalone (non-Laravel) migrator stores its state in a JSON file.
    |
    */
    'migrations' => [
        'path' => 'database/es-migrations',
        'repository' => 'es-orm-migrations.json',
    ],
];
