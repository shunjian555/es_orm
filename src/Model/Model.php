<?php

declare(strict_types=1);

namespace EsOrm\Model;

use ArrayAccess;
use DateTimeImmutable;
use EsOrm\Client\ConnectionManager;
use EsOrm\Contracts\ConnectionInterface;
use EsOrm\Contracts\ConnectionManagerInterface;
use EsOrm\Contracts\EventDispatcherInterface;
use EsOrm\Contracts\MappingInterface;
use EsOrm\Contracts\ModelInterface;
use EsOrm\Events\EventDispatcher;
use EsOrm\Exceptions\BulkException;
use EsOrm\Exceptions\ConnectionException;
use EsOrm\Exceptions\InvalidQueryException;
use EsOrm\Exceptions\ModelNotFoundException;
use EsOrm\Mapping\Mapping;
use EsOrm\Model\Casts\CastFactory;
use EsOrm\Query\Builder;
use EsOrm\Relation\BelongsTo;
use EsOrm\Relation\HasMany;
use EsOrm\Relation\HasOne;
use EsOrm\Relation\Relation;
use EsOrm\Scope\Scope;
use EsOrm\Support\Config;
use EsOrm\Support\Str;
use ReflectionException;
use ReflectionMethod;
use ReflectionNamedType;

/**
 * The Eloquent-style Elasticsearch model.
 *
 * It provides the Active Record surface (`find`, `create`, `save`, `delete`,
 * ...), attribute casting, accessors/mutators, local and global scopes, model
 * events/observers, soft deletes and a batch-aware relationship layer — while
 * delegating every Elasticsearch specific concern to the {@see Builder}, the
 * {@see \EsOrm\Client\Connection} and the DSL layer.
 *
 * A model is a thin, well typed façade: it never talks HTTP directly.
 *
 * @implements ArrayAccess<string, mixed>
 * @phpstan-consistent-constructor Subclasses must not change the constructor signature because models are built internally with `new static()`.
 */
abstract class Model implements ModelInterface, ArrayAccess
{
    /**
     * The Elasticsearch index (or alias) backing this model.
     */
    protected string $index = '';

    /**
     * The connection name; empty means "the default connection".
     */
    protected string $connection = '';

    /**
     * The primary key / document id field name.
     */
    protected string $primaryKey = 'id';

    /**
     * Mass assignable attributes. When empty, only non guarded attributes can
     * be filled and `$guarded` drives the behaviour.
     *
     * @var list<string>
     */
    protected array $fillable = [];

    /**
     * Guarded attributes. `['*']` (the default) makes every attribute guarded
     * unless it appears in `$fillable`.
     *
     * @var list<string>
     */
    protected array $guarded = ['*'];

    /**
     * Attribute casts, e.g. `['age' => 'integer', 'created_at' => 'datetime']`.
     *
     * @var array<string, string>
     */
    protected array $casts = [];

    /**
     * Attributes hidden from array/JSON output.
     *
     * @var list<string>
     */
    protected array $hidden = [];

    /**
     * When non empty, only these attributes appear in array/JSON output.
     *
     * @var list<string>
     */
    protected array $visible = [];

    /**
     * The raw stored attributes.
     *
     * @var array<string, mixed>
     */
    protected array $attributes = [];

    /**
     * The attributes as they were last synced with Elasticsearch.
     *
     * @var array<string, mixed>
     */
    protected array $original = [];

    /**
     * Whether the document exists in Elasticsearch.
     */
    protected bool $exists = false;

    /**
     * Loaded relations, keyed by relation name.
     *
     * @var array<string, mixed>
     */
    protected array $relations = [];

    /**
     * Elasticsearch specific metadata (`_id`, `_index`, `_score`, `sort`, `highlight`).
     *
     * @var array<string, mixed>
     */
    protected array $metadata = [];

    /**
     * Set while a force delete is in progress.
     */
    protected bool $forceDeleting = false;

    /**
     * Per-instance cache of attribute mutator methods.
     *
     * @var array<string, Attribute|false>
     */
    private array $mutatorCache = [];

    private static ?ConnectionManagerInterface $connectionManager = null;

    private static ?EventDispatcherInterface $dispatcher = null;

    /**
     * @var array<class-string, list<Scope>>
     */
    private static array $globalScopes = [];

    /* --------------------------------------------------------------------
     |  Static infrastructure
     * ----------------------------------------------------------------- */

    public static function setConnectionManager(?ConnectionManagerInterface $manager): void
    {
        self::$connectionManager = $manager;
    }

    /**
     * The connection manager shared by every model.
     *
     * When none has been injected, it is lazily built from
     * `config/es-orm.php` (or the file pointed at by `ES_ORM_CONFIG`).
     *
     * @throws ConnectionException When no configuration can be found.
     */
    public static function connectionManager(): ConnectionManagerInterface
    {
        if (self::$connectionManager instanceof ConnectionManagerInterface) {
            return self::$connectionManager;
        }

        $path = getenv('ES_ORM_CONFIG');
        $path = is_string($path) && $path !== '' ? $path : getcwd() . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'es-orm.php';

        if (is_file($path)) {
            return self::$connectionManager = new ConnectionManager(Config::fromFile($path));
        }

        throw new ConnectionException(
            'No Elasticsearch connection manager has been configured. Call `' . static::class . '::setConnectionManager($manager)` or provide a `config/es-orm.php` file.',
        );
    }

    public static function setEventDispatcher(?EventDispatcherInterface $dispatcher): void
    {
        self::$dispatcher = $dispatcher;
    }

    public static function eventDispatcher(): EventDispatcherInterface
    {
        return self::$dispatcher ??= new EventDispatcher();
    }

    /**
     * Register a global scope (applied to every query built for this model).
     */
    public static function addGlobalScope(Scope $scope): void
    {
        self::$globalScopes[static::class][] = $scope;
    }

    /**
     * @return list<Scope>
     */
    public function getGlobalScopes(): array
    {
        return self::$globalScopes[static::class] ?? [];
    }

    /**
     * Register an observer for this model.
     */
    public static function observe(object|string $observer): void
    {
        static::eventDispatcher()->registerObserver(static::class, $observer);
    }

    /* --------------------------------------------------------------------
     |  Static Active Record entry points
     * ----------------------------------------------------------------- */

    public static function query(): Builder
    {
        return (new static())->newQuery();
    }

    public function newQuery(): Builder
    {
        return new Builder(new static(), static::connectionManager());
    }

    /**
     * @return static|null
     */
    public static function find(mixed $id): ?static
    {
        /** @var static|null $model */
        $model = static::query()->where((new static())->getKeyName(), $id)->first();

        return $model;
    }

    /**
     * @return static
     *
     * @throws ModelNotFoundException
     */
    public static function findOrFail(mixed $id): static
    {
        $model = static::find($id);

        if (!$model instanceof static) {
            throw ModelNotFoundException::forModel(static::class, $id);
        }

        return $model;
    }

    /**
     * @return static
     */
    public static function firstOrFail(): static
    {
        $model = static::query()->first();

        if (!$model instanceof static) {
            throw ModelNotFoundException::forModel(static::class, null);
        }

        return $model;
    }

    /**
     * @return static|null
     */
    public static function first(): ?static
    {
        /** @var static|null $model */
        $model = static::query()->first();

        return $model;
    }

    /**
     * @param array<string, mixed> $attributes
     */
    public static function create(array $attributes): static
    {
        $model = new static();
        $model->fill($attributes);
        $model->save();

        return $model;
    }

    /**
     * Eager load relations on a fresh query.
     *
     * @param array<array-key, string>|string $relations
     */
    public static function with(array|string $relations): Builder
    {
        return static::query()->with($relations);
    }

    /**
     * Retrieve every document (subject to the configured default page size).
     */
    public static function all(): ModelCollection
    {
        /** @var ModelCollection $collection */
        $collection = static::query()->get();

        return $collection;
    }

    /**
     * Multi get: fetch many documents by id in a single request.
     *
     * @param array<array-key, mixed> $ids
     */
    public static function mget(array $ids): ModelCollection
    {
        $prototype = new static();

        $response = $prototype->connection()->request('mget', [
            'index' => $prototype->getIndex(),
            'body' => ['ids' => array_values($ids)],
        ]);

        $documents = $response['docs'] ?? [];
        $documents = is_array($documents) ? $documents : [];

        $models = [];

        foreach ($documents as $document) {
            if (!is_array($document) || ($document['found'] ?? false) !== true) {
                continue;
            }

            $models[] = $prototype->newFromHit([
                '_id' => $document['_id'] ?? null,
                '_index' => $document['_index'] ?? null,
                '_source' => $document['_source'] ?? [],
            ]);
        }

        return new ModelCollection($models);
    }

    /**
     * Bulk index many documents in one Bulk API request.
     *
     * @param array<array-key, array<string, mixed>|static> $documents
     * @return ModelCollection The created models (with their ids).
     */
    public static function bulkCreate(array $documents, ?int $chunkSize = null): ModelCollection
    {
        $chunkSize ??= static::defaultBulkChunkSize();
        $prototype = new static();
        $index = $prototype->getIndex();
        $keyName = $prototype->getKeyName();

        $created = [];

        foreach (array_chunk(array_values($documents), max(1, $chunkSize)) as $chunk) {
            $lines = [];

            foreach ($chunk as $document) {
                $model = $document instanceof static ? $document : $prototype->newInstance((array) $document, false);

                $meta = ['_index' => $index];
                $id = $model->getKey();

                if ($id !== null) {
                    $meta['_id'] = $id;
                }

                $lines[] = json_encode(['index' => $meta], JSON_THROW_ON_ERROR);
                $lines[] = json_encode($model->getAttributesForStorage(), JSON_THROW_ON_ERROR);
                $created[] = $model;
            }

            $prototype->performBulk(implode("\n", $lines) . "\n");
        }

        return new ModelCollection($created);
    }

    /**
     * Bulk update many documents in one Bulk API request.
     *
     * @param array<array-key, array<string, mixed>> $documents Each document must contain the primary key.
     */
    public static function bulkUpdate(array $documents, ?int $chunkSize = null): int
    {
        $chunkSize ??= static::defaultBulkChunkSize();
        $prototype = new static();
        $index = $prototype->getIndex();
        $keyName = $prototype->getKeyName();
        $affected = 0;

        foreach (array_chunk(array_values($documents), max(1, $chunkSize)) as $chunk) {
            $lines = [];

            foreach ($chunk as $document) {
                if (!array_key_exists($keyName, $document)) {
                    throw new InvalidQueryException(sprintf('A bulk update document is missing its primary key [%s].', $keyName));
                }

                $id = $document[$keyName];
                unset($document[$keyName]);

                $lines[] = json_encode(['update' => ['_index' => $index, '_id' => $id]], JSON_THROW_ON_ERROR);
                $lines[] = json_encode(['doc' => $document], JSON_THROW_ON_ERROR);
                ++$affected;
            }

            $prototype->performBulk(implode("\n", $lines) . "\n");
        }

        return $affected;
    }

    /**
     * Bulk delete many documents in one Bulk API request.
     *
     * @param array<array-key, mixed> $ids
     */
    public static function bulkDelete(array $ids, ?int $chunkSize = null): int
    {
        $chunkSize ??= static::defaultBulkChunkSize();
        $prototype = new static();
        $index = $prototype->getIndex();
        $affected = 0;

        foreach (array_chunk(array_values($ids), max(1, $chunkSize)) as $chunk) {
            $lines = [];

            foreach ($chunk as $id) {
                $lines[] = json_encode(['delete' => ['_index' => $index, '_id' => $id]], JSON_THROW_ON_ERROR);
                ++$affected;
            }

            $prototype->performBulk(implode("\n", $lines) . "\n");
        }

        return $affected;
    }

    /**
     * Delete the documents with the given ids (soft delete aware).
     *
     * @param array<array-key, int|string>|int|string $ids
     */
    public static function destroy(array|string|int $ids): int
    {
        $ids = is_array($ids) ? $ids : func_get_args();

        return static::bulkDelete($ids);
    }

    /**
     * Forward unknown static calls to a fresh query (`User::where(...)`).
     *
     * @param array<int, mixed> $arguments
     */
    public static function __callStatic(string $method, array $arguments): mixed
    {
        return static::query()->{$method}(...$arguments);
    }

    /* --------------------------------------------------------------------
     |  Identity
     * ----------------------------------------------------------------- */

    public function getIndex(): string
    {
        if ($this->index !== '') {
            return $this->index;
        }

        $short = substr(static::class, (int) strrpos(static::class, '\\') + 1);

        return Str::snake($short) . 's';
    }

    /**
     * The Elasticsearch mapping of this model.
     *
     * Subclasses override this to declare their index mapping, e.g.
     *
     * ```php
     * protected function mapping(): array
     * {
     *     return ['properties' => ['title' => ['type' => 'text']]];
     * }
     * ```
     *
     * @return array<string, mixed>
     */
    public function mapping(): array
    {
        return ['properties' => []];
    }

    /**
     * The model mapping as a {@see MappingInterface} value object.
     */
    public function mappingDefinition(): MappingInterface
    {
        return Mapping::fromArray($this->getIndex(), $this->mapping());
    }

    public function getConnectionName(): string
    {
        return $this->connection;
    }

    public function connection(): ConnectionInterface
    {
        $name = $this->getConnectionName();

        return static::connectionManager()->connection($name === '' ? null : $name);
    }

    public function getKeyName(): string
    {
        return $this->primaryKey;
    }

    public function getKey(): mixed
    {
        return $this->attributes[$this->primaryKey] ?? $this->metadata['_id'] ?? null;
    }

    public function exists(): bool
    {
        return $this->exists;
    }

    public function usesSoftDeletes(): bool
    {
        return in_array(SoftDeletes::class, self::classUsesRecursive(static::class), true);
    }

    /**
     * Every trait used by a class (and its parents), used to detect markers such
     * as {@see SoftDeletes}.
     *
     * @param class-string $class
     * @return list<string>
     */
    private static function classUsesRecursive(string $class): array
    {
        $traits = class_uses($class);

        foreach (class_parents($class) ?: [] as $parent) {
            $traits = array_merge($traits ?: [], class_uses($parent) ?: []);
        }

        return array_values(array_unique($traits ?: []));
    }

    public function getDeletedAtColumn(): string
    {
        return 'deleted_at';
    }

    /* --------------------------------------------------------------------
     |  Attributes: casts, accessors, mutators
     * ----------------------------------------------------------------- */

    public function getAttribute(string $key): mixed
    {
        if ($key === 'highlights') {
            return $this->metadata['highlight'] ?? [];
        }

        if (array_key_exists($key, $this->relations)) {
            return $this->relations[$key];
        }

        $value = $this->attributes[$key] ?? null;

        $cast = $this->casts[$key] ?? null;

        if (is_string($cast)) {
            $value = CastFactory::get($value, $cast);
        }

        $mutator = $this->mutatorFor($key);

        if ($mutator instanceof Attribute && $mutator->hasGetter()) {
            $value = $mutator->get($value, $this->attributes);
        }

        return $value;
    }

    public function setAttribute(string $key, mixed $value): static
    {
        $mutator = $this->mutatorFor($key);

        if ($mutator instanceof Attribute && $mutator->hasSetter()) {
            $value = $mutator->set($value, $this->attributes);
        }

        $cast = $this->casts[$key] ?? null;

        if (is_string($cast)) {
            $value = CastFactory::set($value, $cast);
        }

        $this->attributes[$key] = $value;

        return $this;
    }

    public function fill(array $attributes): static
    {
        foreach ($attributes as $key => $value) {
            if ($this->isFillable((string) $key)) {
                $this->setAttribute((string) $key, $value);
            }
        }

        return $this;
    }

    public function forceFill(array $attributes): static
    {
        foreach ($attributes as $key => $value) {
            $this->setAttribute((string) $key, $value);
        }

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function getAttributes(): array
    {
        return $this->attributes;
    }

    /**
     * The attributes as they will be sent to Elasticsearch.
     *
     * @return array<string, mixed>
     */
    public function getAttributesForStorage(): array
    {
        return $this->attributes;
    }

    /**
     * @param array<string, mixed> $attributes
     */
    public function setRawAttributes(array $attributes, bool $sync = false): static
    {
        $this->attributes = $attributes;

        if ($sync) {
            $this->original = $attributes;
        }

        return $this;
    }

    public function isFillable(string $key): bool
    {
        if (in_array($key, $this->fillable, true)) {
            return true;
        }

        if ($this->isGuarded($key)) {
            return false;
        }

        return $this->fillable === [] && !str_starts_with($key, '_');
    }

    public function isGuarded(string $key): bool
    {
        if ($this->guarded === []) {
            return false;
        }

        return in_array('*', $this->guarded, true) || in_array($key, $this->guarded, true);
    }

    public function getScore(): ?float
    {
        $score = $this->metadata['_score'] ?? null;

        return is_int($score) || is_float($score) ? (float) $score : null;
    }

    public function getIndexName(): ?string
    {
        $index = $this->metadata['_index'] ?? null;

        return is_string($index) ? $index : null;
    }

    /**
     * @return array<string, mixed>
     */
    public function highlights(): array
    {
        $highlight = $this->metadata['highlight'] ?? [];

        return is_array($highlight) ? $highlight : [];
    }

    /**
     * The highlight fragments of a field, or null when the field is not highlighted.
     *
     * @return list<string>|null
     */
    public function highlight(string $field): ?array
    {
        $fragments = $this->highlights()[$field] ?? null;

        return is_array($fragments) ? array_values(array_map('strval', $fragments)) : null;
    }

    /* --------------------------------------------------------------------
     |  Persistence
     * ----------------------------------------------------------------- */

    public function save(): bool
    {
        if ($this->fireModelEvent('saving') === false) {
            return false;
        }

        if ($this->exists) {
            if ($this->fireModelEvent('updating') === false) {
                return false;
            }

            $this->performUpdate();
            $this->fireModelEvent('updated');
        } else {
            if ($this->fireModelEvent('creating') === false) {
                return false;
            }

            $this->performInsert();
            $this->fireModelEvent('created');
        }

        $this->syncOriginal();
        $this->fireModelEvent('saved');

        return true;
    }

    public function update(array $attributes = []): bool
    {
        if ($attributes !== []) {
            $this->fill($attributes);
        }

        return $this->save();
    }

    public function delete(): bool
    {
        if ($this->fireModelEvent('deleting') === false) {
            return false;
        }

        if ($this->usesSoftDeletes() && !$this->forceDeleting) {
            $this->setAttribute($this->getDeletedAtColumn(), self::now());

            if ($this->fireModelEvent('updating') === false) {
                return false;
            }

            $this->performUpdate();
            $this->fireModelEvent('updated');
            $this->fireModelEvent('deleted');

            return true;
        }

        if ($this->usesSoftDeletes()) {
            $this->fireModelEvent('forceDeleting');
        }

        $this->performDelete();

        $this->exists = false;
        $this->fireModelEvent($this->usesSoftDeletes() ? 'forceDeleted' : 'deleted');

        return true;
    }

    public function forceDelete(): bool
    {
        $this->forceDeleting = true;
        $result = $this->delete();
        $this->forceDeleting = false;

        return $result;
    }

    public function refresh(): static
    {
        $key = $this->getKey();

        if ($key === null) {
            return $this;
        }

        $response = $this->connection()->request('get', [
            'index' => $this->getIndex(),
            'id' => $key,
        ]);

        $source = $response['_source'] ?? null;

        if (is_array($source)) {
            if (!array_key_exists($this->primaryKey, $source)) {
                $source[$this->primaryKey] = $key;
            }

            $this->attributes = $source;
            $this->original = $source;
            $this->exists = true;
            $this->metadata['_id'] = $key;
            $this->metadata['_index'] = $response['_index'] ?? $this->metadata['_index'] ?? null;
        }

        return $this;
    }

    /**
     * Create a model from a raw Elasticsearch hit.
     *
     * @param array<string, mixed> $hit
     */
    public function newFromHit(array $hit): static
    {
        $model = new static();
        $source = $hit['_source'] ?? [];
        $source = is_array($source) ? $source : [];

        $id = $hit['_id'] ?? null;

        if ($id !== null && !array_key_exists($model->getKeyName(), $source)) {
            $source[$model->getKeyName()] = $id;
        }

        $model->attributes = $source;
        $model->original = $source;
        $model->exists = true;
        $model->metadata = [
            '_id' => $id,
            '_index' => $hit['_index'] ?? null,
            '_score' => $hit['_score'] ?? null,
            'sort' => $hit['sort'] ?? null,
            'highlight' => $hit['highlight'] ?? [],
        ];

        return $model;
    }

    /**
     * @param array<string, mixed> $attributes
     */
    public function newInstance(array $attributes = [], bool $exists = false): static
    {
        $model = new static();
        $model->setRawAttributes($attributes, true);
        $model->exists = $exists;

        return $model;
    }

    /* --------------------------------------------------------------------
     |  Relations
     * ----------------------------------------------------------------- */

    public function hasOne(string $related, ?string $foreignKey = null, ?string $localKey = null): HasOne
    {
        $instance = new $related();
        $foreignKey ??= $this->defaultForeignKey();
        $localKey ??= $this->primaryKey;

        return new HasOne($this, $instance, $foreignKey, $localKey);
    }

    public function hasMany(string $related, ?string $foreignKey = null, ?string $localKey = null): HasMany
    {
        $instance = new $related();
        $foreignKey ??= $this->defaultForeignKey();
        $localKey ??= $this->primaryKey;

        return new HasMany($this, $instance, $foreignKey, $localKey);
    }

    public function belongsTo(string $related, ?string $foreignKey = null, ?string $ownerKey = null): BelongsTo
    {
        $instance = new $related();
        $foreignKey ??= $this->defaultForeignKey();
        $ownerKey ??= $instance->getKeyName();

        return new BelongsTo($this, $instance, $foreignKey, $ownerKey);
    }

    /**
     * Resolve a relation definition by name.
     *
     * @throws InvalidQueryException When the method does not exist or does not return a Relation.
     */
    public function resolveRelation(string $name): Relation
    {
        if (!method_exists($this, $name)) {
            throw new InvalidQueryException(sprintf('Relation method [%s::%s()] does not exist.', static::class, $name));
        }

        $relation = $this->{$name}();

        if (!$relation instanceof Relation) {
            throw new InvalidQueryException(sprintf('Relation method [%s::%s()] must return an instance of %s.', static::class, $name, Relation::class));
        }

        return $relation;
    }

    public function setRelation(string $relation, mixed $value): static
    {
        $this->relations[$relation] = $value;

        return $this;
    }

    public function getRelation(string $relation): mixed
    {
        return $this->relations[$relation] ?? null;
    }

    public function relationLoaded(string $relation): bool
    {
        return array_key_exists($relation, $this->relations);
    }

    /**
     * Eager load relations on this already loaded model.
     *
     * @param array<array-key, string>|string $relations
     */
    public function load(array|string $relations): static
    {
        foreach (is_array($relations) ? $relations : [$relations] as $name) {
            $relation = $this->resolveRelation($name);
            $relation->setRelationName($name);
            $models = [$this];
            $relation->addEagerConstraints($models);
            $relation->match($models, $relation->getEagerResults());
        }

        return $this;
    }

    /**
     * Eager load relations that have not been loaded yet.
     *
     * @param array<array-key, string>|string $relations
     */
    public function loadMissing(array|string $relations): static
    {
        $missing = array_filter(
            is_array($relations) ? $relations : [$relations],
            fn (string $name): bool => !$this->relationLoaded($name),
        );

        return $missing === [] ? $this : $this->load(array_values($missing));
    }

    /**
     * Whether the model has a loaded relation with the given name/value.
     */
    public function relationLoadedAndNotEmpty(string $relation): bool
    {
        return $this->relationLoaded($relation) && $this->relations[$relation] !== null;
    }

    /* --------------------------------------------------------------------
     |  Events
     * ----------------------------------------------------------------- */

    /**
     * Fire a model event. Returns false when a listener halted the operation.
     */
    protected function fireModelEvent(string $event): bool
    {
        $dispatcher = static::eventDispatcher();

        if (!$dispatcher->hasListeners($event, static::class)) {
            return true;
        }

        return $dispatcher->fire($event, $this);
    }

    /* --------------------------------------------------------------------
     |  Serialization / array access
     * ----------------------------------------------------------------- */

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $result = [];

        foreach (array_keys($this->attributes) as $key) {
            $result[$key] = $this->getAttribute($key);
        }

        foreach ($this->relations as $name => $value) {
            $result[$name] = $this->relationToArray($value);
        }

        if ($this->hidden !== []) {
            $result = array_diff_key($result, array_flip($this->hidden));
        }

        if ($this->visible !== []) {
            $result = array_intersect_key($result, array_flip($this->visible));
        }

        return $result;
    }

    public function toJson(int $options = 0): string
    {
        return json_encode($this->toArray(), $options | JSON_THROW_ON_ERROR);
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    public function __get(string $key): mixed
    {
        return $this->getAttribute($key);
    }

    public function __set(string $key, mixed $value): void
    {
        $this->setAttribute($key, $value);
    }

    public function __isset(string $key): bool
    {
        return isset($this->attributes[$key]) || array_key_exists($key, $this->relations) || $key === 'highlights';
    }

    public function __unset(string $key): void
    {
        unset($this->attributes[$key]);
    }

    public function offsetExists(mixed $offset): bool
    {
        return $this->__isset((string) $offset);
    }

    public function offsetGet(mixed $offset): mixed
    {
        return $this->getAttribute((string) $offset);
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        $this->setAttribute((string) $offset, $value);
    }

    public function offsetUnset(mixed $offset): void
    {
        unset($this->attributes[(string) $offset]);
    }

    /* --------------------------------------------------------------------
     |  Internals
     * ----------------------------------------------------------------- */

    protected function performInsert(): void
    {
        $params = [
            'index' => $this->getIndex(),
            'body' => $this->getAttributesForStorage(),
        ];

        $key = $this->getKey();

        if ($key !== null) {
            $params['id'] = $key;
        }

        $response = $this->connection()->request('index', $params);

        if ($key === null && isset($response['_id'])) {
            $this->attributes[$this->primaryKey] = $response['_id'];
        }

        $this->metadata['_id'] = $response['_id'] ?? $this->getKey();
        $this->metadata['_index'] = $response['_index'] ?? $this->getIndex();
        $this->exists = true;
    }

    protected function performUpdate(): void
    {
        $key = $this->getKey();

        if ($key === null) {
            throw new InvalidQueryException('Cannot update a model that has no primary key value.');
        }

        $this->connection()->request('update', [
            'index' => $this->getIndex(),
            'id' => $key,
            'body' => ['doc' => $this->getAttributesForStorage()],
        ]);
    }

    protected function performDelete(): void
    {
        $key = $this->getKey();

        if ($key === null) {
            return;
        }

        $this->connection()->request('delete', [
            'index' => $this->getIndex(),
            'id' => $key,
        ]);
    }

    /**
     * Execute a pre-built NDJSON bulk body and surface individual failures.
     */
    protected function performBulk(string $body): void
    {
        $response = $this->connection()->request('bulk', ['body' => $body]);

        $items = $response['items'] ?? [];
        $items = is_array($items) ? $items : [];
        $failures = [];

        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }

            foreach ($item as $operation) {
                if (is_array($operation) && ($operation['error'] ?? null) !== null) {
                    $failures[] = $operation;
                }
            }
        }

        if ($failures !== []) {
            throw BulkException::fromFailures($failures);
        }
    }

    protected function syncOriginal(): void
    {
        $this->original = $this->attributes;
    }

    protected static function defaultBulkChunkSize(): int
    {
        $chunk = (new static())->connection()->getConfigValue('bulk.chunk_size', 1000);

        return is_int($chunk) && $chunk > 0 ? $chunk : 1000;
    }

    private function defaultForeignKey(): string
    {
        $short = substr(static::class, (int) strrpos(static::class, '\\') + 1);

        return Str::snake($short) . '_id';
    }

    private function mutatorFor(string $key): ?Attribute
    {
        if (array_key_exists($key, $this->mutatorCache)) {
            $cached = $this->mutatorCache[$key];

            return $cached instanceof Attribute ? $cached : null;
        }

        $method = Str::camel($key);

        if (!method_exists($this, $method) || !$this->declaresAttributeReturnType($method)) {
            $this->mutatorCache[$key] = false;

            return null;
        }

        $mutator = $this->{$method}();

        if (!$mutator instanceof Attribute) {
            $this->mutatorCache[$key] = false;

            return null;
        }

        $this->mutatorCache[$key] = $mutator;

        return $mutator;
    }

    private function declaresAttributeReturnType(string $method): bool
    {
        try {
            $type = (new ReflectionMethod($this, $method))->getReturnType();
        } catch (ReflectionException) {
            return false;
        }

        if (!$type instanceof ReflectionNamedType) {
            return false;
        }

        return $type->getName() === Attribute::class;
    }

    /**
     * @return array<array-key, mixed>|scalar|null
     */
    private function relationToArray(mixed $value): mixed
    {
        if ($value instanceof Model) {
            return $value->toArray();
        }

        if ($value instanceof ModelCollection) {
            return $value->toArray();
        }

        return $value;
    }

    private static function now(): string
    {
        return (new DateTimeImmutable())->format('Y-m-d\TH:i:sP');
    }
}
