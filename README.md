# php-es-orm

An **Eloquent-style, type-safe, production-ready Elasticsearch ORM for PHP**.

`php-es-orm` gives you a Laravel Eloquent-like developer experience on top of
Elasticsearch, without hiding what Elasticsearch can do. It keeps the native
Query DSL, Aggregations, `search_after`, Point In Time (PIT), Bulk, Aliases,
Reindex and everything else within reach — and never pretends Elasticsearch is
MySQL.

- Eloquent-style models: `Model`, `find`, `create`, `save`, `update`, `delete`, …
- A fluent, chainable Query Builder
- Full Elasticsearch Query DSL, including Bool and Nested queries
- A first-class **raw DSL** escape hatch, mixable with the ORM API
- Aggregations, Highlight, Suggest, Geo, Scroll, Search After, PIT
- Bulk API for batch create/update/delete (never a loop of single calls)
- Index / Mapping / Alias / Reindex / Migration management
- Casts, accessors & mutators, scopes, soft deletes, events, observers, relations
- Laravel integration as an **optional** adapter: the core has zero Laravel dependency
- PHP 8.2+, PSR-4 / PSR-12, PHPStan clean

---

## Table of contents

1. [Requirements](#requirements)
2. [Installation](#installation)
3. [Configuration](#configuration)
4. [Connecting to Elasticsearch](#connecting-to-elasticsearch)
5. [Creating a Model](#creating-a-model)
6. [CRUD](#crud)
7. [Query Builder](#query-builder)
8. [Query DSL](#query-dsl)
9. [Bool queries](#bool-queries)
10. [Nested queries](#nested-queries)
11. [Raw DSL](#raw-dsl)
12. [Source filtering](#source-filtering)
13. [Sorting](#sorting)
14. [Operators](#operators)
15. [Pagination](#pagination)
16. [Aggregations](#aggregations)
17. [Highlight](#highlight)
18. [Suggest](#suggest)
19. [Geo search](#geo-search)
20. [Scroll](#scroll)
21. [Bulk](#bulk)
22. [MGET & Multi Search](#mget--multi-search)
23. [Casts](#casts)
24. [Accessors & Mutators](#accessors--mutators)
25. [Fillable / Guarded](#fillable--guarded)
26. [Scopes](#scopes)
27. [Soft deletes](#soft-deletes)
28. [Events & Observers](#events--observers)
29. [Relations](#relations)
30. [Mapping](#mapping)
31. [Index management](#index-management)
32. [Alias management](#alias-management)
33. [Reindex](#reindex)
34. [Index migrations](#index-migrations)
35. [Caching](#caching)
36. [Debugging](#debugging)
37. [Error handling](#error-handling)
38. [Laravel integration](#laravel-integration)
39. [Docker & tooling](#docker--tooling)
40. [Testing](#testing)
41. [FAQ](#faq)
42. [Architecture](#architecture)

---

## Requirements

- PHP **8.2+**
- `ext-json`
- The official [`elasticsearch/elasticsearch`](https://github.com/elastic/elasticsearch-php)
  client (`^9.0`), which is installed by Composer
- Elasticsearch **8.x** (9.x compatibility is preserved where possible)

## Installation

```bash
composer require php-es-orm/es-orm
```

You also need a PSR-18 HTTP client (Guzzle is the usual choice):

```bash
composer require guzzlehttp/guzzle
```

## Configuration

The package ships with a configuration file you can copy into your project:

```bash
cp vendor/php-es-orm/es-orm/config/es_orm.php config/es_orm.php
```

```php
<?php

declare(strict_types=1);

return [
    'default' => 'default',

    'connections' => [
        'default' => [
            'hosts' => ['http://localhost:9200'],
        ],

        // A second connection, used by models declaring `protected string $connection = 'logs';`
        'logs' => [
            'hosts' => ['http://localhost:9201'],
            'api_key' => 'id:key',
        ],
    ],

    'retry' => [
        'max_retries' => 3,
        'retry_delay' => 100, // milliseconds
        'backoff'     => 2,
        'max_delay'   => 2000,
    ],

    'logging' => [
        'enabled'     => true,
        'max_entries' => 100,
    ],

    'cache' => [
        'enabled' => false,
        'driver'  => EsOrm\Support\Cache\ArrayCacheStore::class,
        'prefix'  => 'es_orm:',
        'ttl'     => 60,
    ],

    'pagination' => [
        'default_size'    => 15,
        'max_size'        => 10000,
        'track_total_hits' => true,
        'scroll_keep_alive' => '1m',
        'pit_keep_alive'    => '1m',
    ],

    'bulk' => [
        'chunk_size' => 1000,
        'refresh'    => false,
    ],

    'index' => [
        'default_settings' => [
            'number_of_shards'   => 1,
            'number_of_replicas' => 1,
        ],
    ],

    'mapping' => [
        'strict' => false,
    ],

    'migrations' => [
        'path'       => 'database/es-migrations',
        'repository' => 'es-orm-migrations.json',
    ],
];
```

Supported connection keys: `hosts`, `cloud_id`, `api_key` (+ optional
`api_key_id`), `username`/`password`, `bearer_token`, `http_client`,
`http_client_options`, `timeout`, `connect_timeout`, `ssl_certificate`,
`ssl_key`, `ssl_ca`, `ssl_verification`, `logger`.

> **Authentication recap** — Basic Auth (`username` + `password`), API Key
> (`api_key`), Bearer Token (`bearer_token`), Cloud ID (`cloud_id`) and TLS
> (`ssl_*`) are all supported.

## Connecting to Elasticsearch

### Standalone PHP

Models resolve a shared connection manager lazily from `config/es_orm.php`
(relative to the current working directory) or from the file pointed at by the
`ES_ORM_CONFIG` environment variable:

```bash
export ES_ORM_CONFIG=/absolute/path/to/es_orm.php
```

You can also inject the manager explicitly (recommended in tests and in
custom bootstrapping):

```php
use EsOrm\Client\ConnectionManager;
use EsOrm\Model\Model;
use EsOrm\Support\Config;

Model::setConnectionManager(new ConnectionManager(Config::fromFile('config/es_orm.php')));

// or from an array
Model::setConnectionManager(ConnectionManager::fromArray([
    'default'     => 'default',
    'connections' => ['default' => ['hosts' => ['http://localhost:9200']]],
]));
```

### Laravel

Nothing to do beyond installing the package — the service provider is
auto-discovered. See [Laravel integration](#laravel-integration).

## Creating a Model

```php
<?php

declare(strict_types=1);

namespace App\Models;

use EsOrm\Model\Model;

final class User extends Model
{
    protected string $index = 'users';

    protected array $fillable = [
        'name',
        'email',
        'age',
        'status',
    ];

    protected array $casts = [
        'age'       => 'integer',
        'active'    => 'boolean',
        'metadata'  => 'array',
        'created_at' => 'datetime',
    ];
}
```

The index defaults to the snake-cased class name plus `s` when `$index` is not
set.

## CRUD

```php
use App\Models\User;

// Create
$user = User::create([
    'name'   => 'Tom',
    'email'  => 'tom@example.com',
    'age'    => 20,
    'status' => 'active',
]);

// Read
$user = User::find(1);            // ?User
$user = User::findOrFail(1);      // User (throws ModelNotFoundException)
$user = User::first();
$user = User::firstOrFail();

// Update
$user = User::find(1);
$user->name = 'Tomás';
$user->save();

$user->update(['status' => 'inactive']); // fill + save

// Delete
$user->delete();

// Helpers
$user->exists();     // bool
$user->getKey();     // mixed
$user->refresh();    // re-read from Elasticsearch
$user->toArray();
$user->toJson();
```

### Find-or-create helpers

```php
// Find by attributes or return an unsaved instance hydrated with the values
$user = User::query()->firstOrNew(['email' => 'tom@example.com'], ['name' => 'Tom']);

// Find by attributes or create the document (single index request when missing)
$user = User::query()->firstOrCreate(['email' => 'tom@example.com'], ['name' => 'Tom']);

// Find by attributes and create/update with the given values
$user = User::query()->updateOrCreate(['email' => 'tom@example.com'], ['age' => 21]);
```

### Atomic counters

`increment()` / `decrement()` write the change with a single Painless script
update (`retry_on_conflict` included), so concurrent writers never lose an
update, and the in-memory attributes stay in sync:

```php
$user->increment('login_count');
$user->increment('balance', 25, ['status' => 'vip']); // extra attributes in the same request
$user->decrement('balance', 10);
```

### Batch operations (Bulk API)

All batch helpers use the Elasticsearch **Bulk API** in a single request per
chunk — they never loop over single-document calls:

```php
$users = User::bulkCreate([
    ['name' => 'A', 'status' => 'active'],
    ['name' => 'B', 'status' => 'active'],
]);

$affected = User::bulkUpdate([
    ['id' => 1, 'status' => 'inactive'],
    ['id' => 2, 'status' => 'inactive'],
]);

$affected = User::bulkDelete([1, 2, 3]);

$affected = User::destroy([1, 2, 3]); // alias of bulkDelete
```

The chunk size defaults to `bulk.chunk_size` (1000) and can be overridden per
call: `User::bulkCreate($docs, 500)`.

## Query Builder

```php
use App\Models\User;

$users = User::query()
    ->where('status', 'active')
    ->where('age', '>', 18)
    ->orderByDesc('created_at')
    ->limit(20)
    ->get();
```

Every `where()` appends to the current AND group; every `orWhere()` starts a new
group. Nested closures reuse the exact same builder:

```php
User::query()
    ->where('status', 'active')
    ->where(function ($query) {
        $query->where('age', '>', 18)
              ->orWhere('vip', true);
    })
    ->get();
```

Available where helpers:

```php
where(), orWhere()            // '=', '>', '>=', '<', '<=', '!=', 'like', 'not like', 'in', 'not in'
whereIn(), whereNotIn()
whereNull(), whereNotNull()
whereBetween(), whereNotBetween()
whereExists(), whereNotExists()
whereNested(), orWhereNested()
```

### Execution helpers

```php
// Existence and single-record assertions
User::query()->where('status', 'active')->exists(); // bool
User::query()->where('email', $email)->sole();      // User, throws when 0 or > 1 match

// Metric shortcuts (a size-0 search with a single value aggregation)
User::query()->where('status', 'active')->avg('age');    // int|float|null
User::query()->sum('price');
User::query()->min('created_at');
User::query()->max('created_at');

// Delete every document matching the query (_delete_by_query, hard delete)
User::query()->where('status', 'banned')->deleteByQuery(['refresh' => true]);
```

`sole()` throws `ModelNotFoundException` when nothing matches and
`MultipleRecordsFoundException` when more than one document matches.

## Query DSL

The native Elasticsearch queries are first-class citizens:

```php
Article::query()
    ->match('title', 'Elasticsearch')
    ->matchPhrase('excerpt', 'full text phrase')
    ->multiMatch(['title', 'body'], 'Elasticsearch ORM')
    ->term('status', 'published')
    ->terms('category_id', [1, 2, 3])
    ->range('created_at', ['gte' => 'now-30d'])
    ->whereExists('published_at')
    ->prefix('slug', 'elastic')
    ->wildcard('title', 'elastic*')
    ->regexp('slug', 'elas.*')
    ->fuzzy('title', 'elasticseach', ['fuzziness' => 'AUTO'])
    ->ids([10, 20, 30])
    ->queryString('title:elasticsearch AND status:published', ['fields' => ['title^2']])
    ->simpleQueryString('elasticsearch +orm', ['fields' => ['title', 'body']])
    ->nested('attributes', fn ($q) => $q->term('attributes.name', 'color'))
    ->parentId('article', 42)
    ->hasChild('comment', fn ($q) => $q->term('approved', true))
    ->hasParent('article', fn ($q) => $q->term('status', 'published'))
    ->get();
```

## Bool queries

The Eloquent-style closure form and the explicit bool-occurrence form both
compile to the Elasticsearch `bool` DSL:

```php
Article::query()
    ->bool(function ($query) {
        $query->must(['term' => ['status' => 'published']]);
        $query->filter(['range' => ['created_at' => ['gte' => 'now-30d']]]);
        $query->should(['match' => ['title' => 'elasticsearch']]);
        $query->mustNot(['term' => ['flagged' => true]]);
    })
    ->minimumShouldMatch(1)
    ->get();

// Explicit occurrences
Article::query()
    ->must(['term' => ['status' => 'published']])
    ->filter(['term' => ['tenant_id' => 7]])
    ->should(['match' => ['title' => 'orm']])
    ->mustNot(['exists' => ['field' => 'deleted_at']])
    ->get();
```

## Nested queries

```php
Product::query()
    ->nested('attributes', function ($query) {
        $query->term('attributes.name', 'color')
              ->term('attributes.value', 'red');
    }, ['score_mode' => 'avg'])
    ->get();
```

## Raw DSL

Anything the ORM does not wrap is still reachable, and it can be mixed with the
ORM API in the same query. Raw DSL is an **advanced API** — your input is sent
as-is.

```php
Article::query()
    ->match('title', 'Elasticsearch')
    ->rawQuery([
        'bool' => [
            'must' => [
                ['term' => ['locale' => 'en']],
            ],
        ],
    ])
    ->aggregate('by_status', ['terms' => ['field' => 'status']])
    ->get();

// Or merge a whole raw request body fragment
Article::query()->raw([
    'query' => ['bool' => ['must' => [...]]],
    'aggs'  => [...],
])->get();
```

## Source filtering

```php
User::query()
    ->select(['id', 'name', 'email']) // -> _source.includes
    ->get();

User::query()
    ->addSelect('avatar')
    ->selectExcept(['password', 'internal_notes']) // -> _source.excludes
    ->get();
```

## Sorting

```php
User::query()->orderBy('name')->get();
User::query()->orderByAsc('name')->get();
User::query()->orderByDesc('created_at')->get();

// Multiple fields
Article::query()->orderBy([
    'created_at' => 'desc',
    'price'      => 'asc',
])->get();

// Full Elasticsearch sort DSL
Article::query()->sort('price', 'asc', ['missing' => '_last', 'mode' => 'avg'])->get();
Article::query()->orderByDesc('_score')->get();
```

## Operators

| ORM operator | Elasticsearch DSL |
|--------------|-------------------|
| `=`, `==`    | `term`            |
| `!=`, `<>`   | `bool.must_not / term` |
| `>`, `>=`, `<`, `<=` | `range`   |
| `like`       | `wildcard` (`%` → `*`) |
| `not like`   | `bool.must_not / wildcard` |
| `in`         | `terms`           |
| `not in`     | `bool.must_not / terms` |

## Pagination

```php
// Length aware (knows the total)
$page = User::query()->where('status', 'active')->paginate(20);
$page->items();      // ModelCollection
$page->total();      // int
$page->currentPage();

// Simple (next / previous only)
$page = User::query()->simplePaginate(20);

// Cursor pagination built on search_after (deep pagination safe)
$page = Article::query()
    ->orderByDesc('created_at')
    ->cursorPaginate(20);

$page->nextCursor(); // pass it back to continue
$next = Article::query()
    ->orderByDesc('created_at')
    ->cursorPaginate(20, $page->nextCursor());
```

Deep pagination primitives:

```php
// search_after
Article::query()->orderByDesc('created_at')->searchAfter([/* ... */])->get();

// Point In Time
$pit = $connection->request('openPointInTime', ['index' => 'articles', 'keep_alive' => '1m'])['id'];

Article::query()->pit($pit)->orderByDesc('created_at')->get();

$connection->request('closePointInTime', ['body' => ['id' => $pit]]);
```

> Elasticsearch's `from + size` pagination is capped (`index.max_result_window`,
> 10 000 by default). Prefer `cursorPaginate()` / `search_after()` / `pit()` for
> large result sets.

## Aggregations

Shorthand builders:

```php
Product::query()
    ->termsAgg('categories', 'category_id')
    ->statsAgg('price_stats', 'price')
    ->avgAgg('avg_price', 'price')
    ->sumAgg('total_stock', 'stock')
    ->minAgg('min_price', 'price')
    ->maxAgg('max_price', 'price')
    ->cardinalityAgg('unique_users', 'user_id')
    ->dateHistogramAgg('sales', 'created_at', 'day')
    ->histogramAgg('price_histogram', 'price', 50)
    ->rangeAgg('price_ranges', 'price', [['to' => 50], ['from' => 50, 'to' => 100]])
    ->dateRangeAgg('recent', 'created_at', [['from' => 'now-7d/d', 'to' => 'now/d']])
    ->filtersAgg('by_status', ['active' => ['term' => ['status' => 'active']]])
    ->nestedAgg('attributes', 'attributes')
    ->reverseNestedAgg('root')
    ->topHitsAgg('top_products', 3)
    ->percentilesAgg('price_percentiles', 'price', [50, 95, 99])
    ->significantTermsAgg('trending', 'tags')
    ->filterAgg('published', fn ($q) => $q->term('status', 'published'))
    ->get();
```

Complex, nested aggregations:

```php
$result = Article::query()
    ->aggregate('sales', function ($agg) {
        $agg->dateHistogram('per_day', 'created_at', 'day');
        $agg->avg('avg_price', 'price'); // became a sub-aggregation of 'sales'
    })
    ->get();

$result->aggregation('sales');  // mixed
$result->aggregations();        // array<string, mixed>
```

## Highlight

```php
$articles = Article::query()
    ->match('content', 'Elasticsearch')
    ->highlight(['title', 'content'], [
        'pre_tags'           => ['<em>'],
        'post_tags'          => ['</em>'],
        'fragment_size'      => 150,
        'number_of_fragments' => 3,
        'type'               => 'unified',
    ])
    ->get();

foreach ($articles as $article) {
    $article->highlight('content'); // list<string>|null
    $article->highlights;           // array<string, list<string>>
}
```

## Suggest

```php
$result = Article::query()
    ->suggest([
        'title_suggest' => [
            'text'      => 'elasticseach',
            'term'      => ['field' => 'title'],
            // 'phrase'  => ['field' => 'body'],
            // 'completion' => ['field' => 'suggest_field'],
        ],
    ])
    ->search();

$result->suggest();
```

## Geo search

```php
Store::query()
    ->geoDistance('location', 10, 35.68, 139.76) // field, distance(km), lat, lon
    ->get();

Store::query()
    ->geoDistance('location', '2km', ['lat' => 35.68, 'lon' => 139.76])
    ->get();

Store::query()
    ->geoBoundingBox('location', ['top_left' => [139.7, 35.7], 'bottom_right' => [139.8, 35.6]])
    ->get();

Store::query()
    ->geoPolygon('location', [[139.7, 35.7], [139.8, 35.7], [139.8, 35.6], [139.7, 35.6]])
    ->get();
```

## Scroll

`each()` scrolls automatically when no `searchAfter()`/`pit()` is configured,
and uses `search_after` (with a PIT) otherwise. Scroll handles are always
cleared:

```php
User::query()->each(function (User $user): void {
    // export / migrate / batch process
}, chunkSize: 1000);

// Break out of the iteration by returning false
User::query()->each(function (User $user): bool {
    return $user->age < 100; // stop at the first user older than 100
});
```

## Bulk

```php
$users = User::bulkCreate([...]);
$count = User::bulkUpdate([...]); // each row must contain the primary key
$count = User::bulkDelete([...]);
```

Individual failures are surfaced through `EsOrm\Exceptions\BulkException`
(`->failures()`).

## MGET & Multi Search

```php
// Multi get (single request)
$users = User::mget([1, 2, 3]);

// Multi search: one _msearch request for many queries
use EsOrm\Query\Search;

$results = Search::multi([
    'users'    => User::query()->match('name', 'Tom'),
    'articles' => Article::query()->term('status', 'published'),
]);

$results['users']->hits();    // ModelCollection
$results['users']->total();   // int
```

## Casts

```php
protected array $casts = [
    'age'        => 'integer',
    'price'      => 'float',
    'active'     => 'boolean',
    'name'       => 'string',
    'metadata'   => 'array',
    'options'    => 'json',
    'created_at' => 'datetime',
    'birthday'   => 'date',
    'profile'    => 'object',
];
```

Supported cast names: `integer`/`int`, `float`/`double`/`decimal`, `boolean`/`bool`,
`string`, `array`, `json`, `object`/`map`, `datetime`/`date_time`/`timestamp`, `date`.

## Accessors & Mutators

```php
use EsOrm\Model\Attribute;

final class User extends Model
{
    protected function name(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => ucfirst((string) $value),
            set: fn ($value) => strtolower((string) $value),
        );
    }
}
```

The getter also receives the whole attribute array as its second argument, so
cross-field accessors are possible. The method must declare an `Attribute`
return type to be recognised.

## Fillable / Guarded

```php
protected array $fillable = ['name', 'email'];
protected array $guarded = ['*']; // default: everything is guarded unless fillable

$user->fill(['name' => 'Tom', 'role' => 'admin']); // 'role' is ignored
$user->forceFill(['role' => 'admin']);             // bypasses the guard
```

## Scopes

Local scopes are plain methods prefixed with `scope`:

```php
final class User extends Model
{
    public function scopeActive($query)
    {
        return $query->term('status', 'active');
    }
}

User::active()->get();        // via __callStatic -> query()
User::query()->active()->get();
```

Object scopes implement `EsOrm\Scope\Scope`; global scopes are registered on the
model and applied to every query:

```php
use EsOrm\Scope\Scope;

Model::addGlobalScope(new class implements Scope {
    public function apply(\EsOrm\Contracts\QueryBuilderInterface $builder): void
    {
        $builder->term('tenant_id', 7);
    }
});
```

## Soft deletes

```php
use EsOrm\Model\Model;
use EsOrm\Model\SoftDeletes;

final class User extends Model
{
    use SoftDeletes;

    protected string $index = 'users';
    protected array $casts = ['deleted_at' => 'datetime'];
}

$user->delete();          // sets deleted_at, keeps the document
$user->trashed();         // bool
$user->restore();         // clears deleted_at
$user->forceDelete();     // removes the document

User::query()->get();            // excludes trashed
User::withTrashed()->get();      // includes trashed
User::onlyTrashed()->get();      // only trashed
```

## Events & Observers

Model events are dispatched through `EsOrm\Events\EventDispatcher`:
`creating`, `created`, `saving`, `saved`, `updating`, `updated`, `deleting`,
`deleted`, `restoring`, `restored`, `forceDeleting`, `forceDeleted`.

```php
use EsOrm\Model\Model;

Model::eventDispatcher()->listen('created', function (Model $model): void {
    // ...
});

// A listener returning false aborts the operation (e.g. saving/creating/deleting)

// Observers
final class UserObserver
{
    public function created(User $user): void {}
    public function updated(User $user): void {}
    public function deleted(User $user): void {}
}

User::observe(UserObserver::class);
```

A Laravel application may replace the dispatcher with a bridge to the framework
event system via `Model::setEventDispatcher()`.

## Relations

Relations are a batch-aware convenience layer (not SQL joins) designed so eager
loading never triggers an N+1:

```php
final class Article extends Model
{
    protected string $index = 'articles';

    public function author(): \EsOrm\Relation\BelongsTo
    {
        return $this->belongsTo(Author::class, 'author_id');
    }
}

final class Author extends Model
{
    protected string $index = 'authors';

    public function articles(): \EsOrm\Relation\HasMany
    {
        return $this->hasMany(Article::class, 'author_id');
    }
}

// Eager load in a single batch query
$articles = Article::with('author')->get();

// Lazy eager load on loaded models
$article->load('author');
$article->loadMissing('author');
```

Available: `HasOne`, `HasMany`, `BelongsTo`. Nested eager loads are supported via
dot notation (`Article::with('author.company')`).

## Mapping

A model declares its mapping; convert it to an index body with
`mappingDefinition()` or hand the raw array to the `IndexManager`.

```php
final class Article extends Model
{
    protected string $index = 'articles';

    protected function mapping(): array
    {
        return [
            'properties' => [
                'title' => [
                    'type'   => 'text',
                    'fields' => ['keyword' => ['type' => 'keyword']],
                ],
                'status'     => ['type' => 'keyword'],
                'created_at' => ['type' => 'date'],
                'price'      => ['type' => 'double'],
            ],
        ];
    }
}

$article->mappingDefinition(); // EsOrm\Contracts\MappingInterface
```

Or build a mapping fluently:

```php
use EsOrm\Mapping\Mapping;

$mapping = Mapping::create('articles')
    ->text('title', ['fields' => ['keyword' => ['type' => 'keyword']]])
    ->keyword('status')
    ->date('created_at')
    ->double('price')
    ->nested('attributes')
    ->geoPoint('location');
```

## Index management

```php
use EsOrm\Index\IndexManager;

$indices = IndexManager::on(Model::connectionManager()); // or Es::index() in Laravel

$indices->createIndex('articles', $mapping, ['number_of_replicas' => 0]);
$indices->indexExists('articles');
$indices->getIndex('articles');
$indices->getMapping('articles');
$indices->putMapping('articles', $extraMapping);
$indices->refresh('articles');
$indices->flush('articles');
$indices->forceMerge('articles', ['max_num_segments' => 1]);
$indices->close('articles');
$indices->open('articles');
$indices->deleteIndex('articles');
```

## Alias management

```php
use EsOrm\Index\AliasManager;

$aliases = AliasManager::on(Model::connectionManager());

$aliases->createAlias('articles_v1', 'articles');
$aliases->switchAlias('articles', 'articles_v2'); // atomic, auto-detects current index
$aliases->deleteAlias('articles_v2', 'articles');
$aliases->getAlias('articles');
```

## Reindex

```php
use EsOrm\Index\ReindexManager;

$reindex = ReindexManager::on(Model::connectionManager());

$reindex->reindexTo('articles_v1', 'articles_v2', [
    'query'      => ['term' => ['status' => 'published']],
    'batch_size' => 1000,
    'script'     => ['source' => 'ctx._source.migrated = true'],
    'conflicts'  => 'proceed',
    'refresh'    => true,
]);

// Zero-downtime upgrade:
$indices->createIndex('articles_v2', $newMapping);
$reindex->reindexTo('articles_v1', 'articles_v2');
$aliases->switchAlias('articles', 'articles_v2');
```

## Index migrations

```php
<?php

declare(strict_types=1);

namespace Database\EsMigrations;

use EsOrm\Migration\IndexMigration;
use EsOrm\Mapping\Mapping;

final class CreateArticlesIndex extends IndexMigration
{
    public function up(): void
    {
        $this->createIndex('articles', Mapping::create('articles')
            ->text('title')
            ->keyword('status')
            ->date('created_at'));
    }

    public function down(): void
    {
        $this->deleteIndex('articles');
    }
}
```

Run them through the framework-agnostic runner:

```php
use EsOrm\Migration\Migrator;

$migrator = new Migrator($indices, 'database/es-migrations');

$migrator->migrate();          // apply pending migrations
$migrator->rollback(1);        // roll back the last batch
$migrator->status();           // list the discovered migrations and their state
```

Or via Artisan (Laravel): `php artisan es:migrate`,
`php artisan es:migrate:rollback`, `php artisan es:migrate:status`.

## Caching

Query caching is **disabled by default**. Enable it with an opt-in per query and
a configurable driver implementing `EsOrm\Contracts\CacheStoreInterface`:

```php
Article::query()
    ->where('status', 'active')
    ->cache(60)        // seconds, optional custom key as 2nd argument
    ->get();
```

## Debugging

```php
$query = User::query()->where('status', 'active');

$query->toDsl();   // array<string, mixed>
$query->toJson();  // string
$query->dump();    // pretty print (VarDumper aware)
$query->dd();      // dump and die
```

The connection records every request (HTTP method, endpoint, index, body,
duration and errors) into `EsOrm\Support\QueryLog` when `logging.enabled` is on.

## Error handling

| Exception | Thrown when |
|-----------|-------------|
| `ConnectionException` | transport failure, server 5xx, retry exhaustion |
| `QueryException` | a non-index / non-bulk request failed |
| `IndexException` | an index-level request failed |
| `BulkException` | a bulk request failed (`->failures()` exposes per-item errors) |
| `MappingException` | an invalid mapping or unsupported cast type |
| `InvalidQueryException` | an invalid builder call / unknown operator |
| `ModelNotFoundException` | `findOrFail()` / `firstOrFail()` found nothing |
| `ValidationException` | invalid input rejected before hitting Elasticsearch |
| `SearchException`, `PaginationException`, `DslException`, `ReindexException` | other specific failures |

All extend `EsOrm\Exceptions\EsOrmException`.

## Laravel integration

The core has no Laravel dependency; the integration is an adapter registered by
package discovery.

```php
use EsOrm\Laravel\Facades\Es;

Es::connection();          // default connection
Es::connection('logs');    // named connection
Es::index();               // IndexManager
Es::alias();               // AliasManager
Es::reindex();             // ReindexManager
Es::migrator();            // Migrator
Es::search([...]);         // raw search
```

Publish the config:

```bash
php artisan vendor:publish --tag=es_orm-config
```

```bash
AppServiceProvider.php
use EsOrm\Contracts\ConnectionManagerInterface;
public function register(): void
{    
    $this->app->make(ConnectionManagerInterface::class);
}
```

Models work unchanged inside a Laravel project.

## Docker & tooling

```bash
make install         # composer install
make elasticsearch   # start Elasticsearch (+ Kibana) via docker compose
make test            # PHPUnit
make analyse         # PHPStan
make cs              # coding standard dry-run
make fix             # apply coding standard fixes
make clean           # remove caches / artefacts
```

`docker-compose.yml` provides Elasticsearch 8.15 and Kibana (profile `kibana`).

## Testing

```bash
# Unit tests (no Elasticsearch needed, everything is mocked)
php vendor/bin/phpunit --testsuite Unit

# Integration tests (require a running Elasticsearch)
ES_INTEGRATION=true php vendor/bin/phpunit --testsuite Integration
```

## FAQ

**Do I need Laravel to use this package?**
No. The core ORM is framework agnostic. Laravel is an optional adapter.

**Does it replace the Elasticsearch Query DSL?**
No. The DSL is fully reachable, and `rawQuery()` / `raw()` let you pass any DSL
the ORM does not wrap.

**How do I avoid deep pagination problems?**
Use `cursorPaginate()`, `searchAfter()` or `pit()` instead of large `offset()`
values.

**Are bulk operations really batched?**
Yes — every `bulkCreate()`/`bulkUpdate()`/`bulkDelete()` call issues Bulk API
requests, one per chunk.

**How do I change the default page size?**
Set `pagination.default_size` in `config/es_orm.php`, or pass it to
`paginate()`.

**Is `rawQuery()` safe?**
It is an advanced API: input is forwarded to Elasticsearch as-is. Use it only
with trusted input; normal user input should go through the DSL builder.

## Architecture

```
Model  →  Query Builder  →  DSL  →  Connection  →  Elasticsearch Client
```

- **Model** is a thin, typed façade: it never performs HTTP.
- **Query Builder** accumulates DSL value objects and compiles a single request body.
- **DSL** value objects (`MatchQuery`, `TermsQuery`, `NestedQuery`, aggregations, sorts, …) each expose `toArray()`.
- **Connection** owns transport concerns: retry, logging, exception mapping.
- **Client** is a thin adapter over the official Elasticsearch client.
- **Laravel** integration is a separate adapter on top.

This separation keeps the API stable and lets new Elasticsearch features be added
without breaking existing code.

## License

MIT — see [LICENSE](LICENSE).

