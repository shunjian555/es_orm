<?php

declare(strict_types=1);

namespace EsOrm\Query;

use Closure;
use EsOrm\Contracts\AggregationInterface;
use EsOrm\Contracts\CacheStoreInterface;
use EsOrm\Contracts\ConnectionInterface;
use EsOrm\Contracts\ConnectionManagerInterface;
use EsOrm\Contracts\DslInterface;
use EsOrm\Contracts\ModelInterface;
use EsOrm\Contracts\QueryBuilderInterface;
use EsOrm\DSL\Dsl;
use EsOrm\DSL\Query\BoolQuery;
use EsOrm\DSL\Query\ExistsQuery;
use EsOrm\DSL\Query\FuzzyQuery;
use EsOrm\DSL\Query\GeoBoundingBoxQuery;
use EsOrm\DSL\Query\GeoDistanceQuery;
use EsOrm\DSL\Query\GeoPolygonQuery;
use EsOrm\DSL\Query\HasChildQuery;
use EsOrm\DSL\Query\HasParentQuery;
use EsOrm\DSL\Query\IdsQuery;
use EsOrm\DSL\Query\MatchPhraseQuery;
use EsOrm\DSL\Query\MatchQuery;
use EsOrm\DSL\Query\MultiMatchQuery;
use EsOrm\DSL\Query\NestedQuery;
use EsOrm\DSL\Query\ParentIdQuery;
use EsOrm\DSL\Query\PrefixQuery;
use EsOrm\DSL\Query\QueryStringQuery;
use EsOrm\DSL\Query\RangeQuery;
use EsOrm\DSL\Query\RawQuery;
use EsOrm\DSL\Query\RegexpQuery;
use EsOrm\DSL\Query\SimpleQueryStringQuery;
use EsOrm\DSL\Query\TermQuery;
use EsOrm\DSL\Query\TermsQuery;
use EsOrm\DSL\Query\WildcardQuery;
use EsOrm\Exceptions\InvalidQueryException;
use EsOrm\Model\Model;
use EsOrm\Model\ModelCollection;
use EsOrm\Model\SearchResult;
use EsOrm\Query\Pagination\CursorPaginator;
use EsOrm\Query\Pagination\LengthAwarePaginator;
use EsOrm\Query\Pagination\Paginator;
use EsOrm\Scope\Scope;
use EsOrm\Support\Arr;

/**
 * The fluent Elasticsearch Query Builder.
 *
 * It mixes an Eloquent-like surface (`where`, `orderBy`, `paginate`, ...) with
 * the native Elasticsearch query DSL (`match`, `term`, `nested`, `aggregate`,
 * `rawQuery`, ...). Internally the builder accumulates small DSL value objects
 * and compiles them into a single request body, so adding new Elasticsearch
 * features never breaks the existing API.
 *
 * @phpstan-type SortDirection 'asc'|'desc'
 */
final class Builder implements QueryBuilderInterface
{
    /**
     * Query clauses grouped for OR semantics: every call to `where()` appends
     * to the current group, every call to `orWhere()` starts a new one.
     *
     * @var list<list<array<string, mixed>>>
     */
    private array $groups = [[]];

    /**
     * Explicit bool occurrences.
     *
     * @var list<array<string, mixed>>
     */
    private array $must = [];

    /**
     * @var list<array<string, mixed>>
     */
    private array $mustNot = [];

    /**
     * @var list<array<string, mixed>>
     */
    private array $should = [];

    /**
     * @var list<array<string, mixed>>
     */
    private array $filter = [];

    private int|string|null $minimumShouldMatch = null;

    /**
     * Raw query fragments registered through `rawQuery()`.
     *
     * @var list<array<string, mixed>>
     */
    private array $rawQueries = [];

    /**
     * Raw request body fragments merged last (advanced API).
     *
     * @var array<string, mixed>
     */
    private array $rawBody = [];

    private AggregationBuilder $aggregations;

    private SortBuilder $sorts;

    private HighlightBuilder $highlight;

    /**
     * @var array<string, mixed>
     */
    private array $suggest = [];

    /**
     * @var list<string>|null
     */
    private ?array $sourceIncludes = null;

    /**
     * @var list<string>
     */
    private array $sourceExcludes = [];

    private ?int $size = null;

    private int $from = 0;

    /**
     * @var array<int|string, mixed>
     */
    private array $searchAfter = [];

    private ?string $pitId = null;

    private string $pitKeepAlive = '1m';

    private ?int $scrollSize = null;

    private string $scrollKeepAlive = '1m';

    /**
     * null = default (exclude trashed), true = withTrashed, false = onlyTrashed.
     */
    private ?bool $trashed = null;

    /**
     * @var list<string>
     */
    private array $eagerLoads = [];

    private ?int $cacheTtl = null;

    private ?string $cacheKey = null;

    private ?CacheStoreInterface $cacheStore = null;

    private bool $trackTotalHits = true;

    public function __construct(
        private readonly Model $model,
        private readonly ConnectionManagerInterface $connections,
        private ?string $index = null,
        private ?string $connectionName = null,
    ) {
        $this->aggregations = new AggregationBuilder();
        $this->sorts = new SortBuilder();
        $this->highlight = new HighlightBuilder();

        foreach ($this->model->getGlobalScopes() as $scope) {
            $this->applyScope($scope);
        }
    }

    /**
     * The model prototype being queried.
     */
    public function getModel(): Model
    {
        return $this->model;
    }

    public function getIndex(): string
    {
        return $this->index ?? $this->model->getIndex();
    }

    public function from(string $index): static
    {
        $this->index = $index;

        return $this;
    }

    public function connection(string $name): static
    {
        $this->connectionName = $name;

        return $this;
    }

    /* --------------------------------------------------------------------
     |  Eloquent style where()
     * ----------------------------------------------------------------- */

    public function where(array|string|Closure $column, mixed $operator = null, mixed $value = null): static
    {
        if (func_num_args() === 2) {
            $value = $operator;
            $operator = '=';
        }

        $this->addWhere($column, $operator, $value, false);

        return $this;
    }

    public function orWhere(array|string|Closure $column, mixed $operator = null, mixed $value = null): static
    {
        if (func_num_args() === 2) {
            $value = $operator;
            $operator = '=';
        }

        $this->addWhere($column, $operator, $value, true);

        return $this;
    }

    public function whereIn(string $column, array $values): static
    {
        return $this->push(new TermsQuery($column, array_values($values)));
    }

    public function whereNotIn(string $column, array $values): static
    {
        return $this->mustNot(new TermsQuery($column, array_values($values)));
    }

    public function whereNull(string $column): static
    {
        return $this->mustNot(new ExistsQuery($column));
    }

    public function whereNotNull(string $column): static
    {
        return $this->push(new ExistsQuery($column));
    }

    public function whereBetween(string $column, array $values): static
    {
        $values = array_values($values);

        return $this->push(new RangeQuery($column, [
            'gte' => $values[0] ?? null,
            'lte' => $values[1] ?? null,
        ]));
    }

    public function whereNotBetween(string $column, array $values): static
    {
        $values = array_values($values);

        return $this->mustNot(new RangeQuery($column, [
            'gte' => $values[0] ?? null,
            'lte' => $values[1] ?? null,
        ]));
    }

    public function whereExists(string $column): static
    {
        return $this->push(new ExistsQuery($column));
    }

    public function whereNotExists(string $column): static
    {
        return $this->mustNot(new ExistsQuery($column));
    }

    public function whereNested(Closure $callback): static
    {
        return $this->push($this->compileClosure($callback));
    }

    public function orWhereNested(Closure $callback): static
    {
        return $this->pushOr($this->compileClosure($callback));
    }

    /* --------------------------------------------------------------------
     |  Elasticsearch query DSL
     * ----------------------------------------------------------------- */

    public function match(string $field, string $query, array $options = []): static
    {
        return $this->push(new MatchQuery($field, $query, $options));
    }

    public function matchPhrase(string $field, string $query, array $options = []): static
    {
        return $this->push(new MatchPhraseQuery($field, $query, $options));
    }

    public function multiMatch(array|string $fields, string $query, array $options = []): static
    {
        return $this->push(new MultiMatchQuery($fields, $query, $options));
    }

    public function term(string $field, mixed $value, array $options = []): static
    {
        return $this->push(new TermQuery($field, $value, $options));
    }

    public function terms(string $field, array $values): static
    {
        return $this->push(new TermsQuery($field, array_values($values)));
    }

    public function range(string $field, array $ranges): static
    {
        return $this->push(new RangeQuery($field, $ranges));
    }

    public function exists(string $field): static
    {
        return $this->push(new ExistsQuery($field));
    }

    public function prefix(string $field, string $value, array $options = []): static
    {
        return $this->push(new PrefixQuery($field, $value, $options));
    }

    public function wildcard(string $field, string $value, array $options = []): static
    {
        return $this->push(new WildcardQuery($field, $value, $options));
    }

    public function regexp(string $field, string $value, array $options = []): static
    {
        return $this->push(new RegexpQuery($field, $value, $options));
    }

    public function fuzzy(string $field, string $value, array $options = []): static
    {
        return $this->push(new FuzzyQuery($field, $value, $options));
    }

    public function ids(array $ids): static
    {
        return $this->push(new IdsQuery(array_values($ids)));
    }

    public function queryString(string $query, array $options = []): static
    {
        return $this->push(new QueryStringQuery($query, $options));
    }

    public function simpleQueryString(string $query, array $options = []): static
    {
        return $this->push(new SimpleQueryStringQuery($query, $options));
    }

    public function nested(string $path, Closure $callback, array $options = []): static
    {
        return $this->push(new NestedQuery($path, $this->compileClosure($callback), $options));
    }

    /**
     * @param array<string, mixed> $options
     */
    public function parentId(string $type, string|int $id, array $options = []): static
    {
        return $this->push(new ParentIdQuery($type, $id, $options));
    }

    /**
     * @param Closure|DslInterface|array<string, mixed> $query
     * @param array<string, mixed> $options
     */
    public function hasChild(string $type, Closure|DslInterface|array $query, array $options = []): static
    {
        return $this->push(new HasChildQuery($type, $this->normalizeClause($query), $options));
    }

    /**
     * @param Closure|DslInterface|array<string, mixed> $query
     * @param array<string, mixed> $options
     */
    public function hasParent(string $parentType, Closure|DslInterface|array $query, array $options = []): static
    {
        return $this->push(new HasParentQuery($parentType, $this->normalizeClause($query), $options));
    }

    /* --------------------------------------------------------------------
     |  Geo
     * ----------------------------------------------------------------- */

    /**
     * Geo distance query.
     *
     * Accepts either an explicit location (string `"lat,lon"`, geo-hash or a
     * `[lon, lat]` array) or the `(field, distance, lat, lon)` shorthand.
     *
     * @param array<array-key, mixed>|string $location
     * @param array<array-key, mixed>|string|int|float|null $longitude
     * @param array<string, mixed> $options
     */
    public function geoDistance(
        string $field,
        int|float|string $distance,
        array|string|int|float $location,
        array|string|int|float|null $longitude = null,
        array $options = [],
    ): static {
        $point = $location;

        if ($longitude !== null && !is_array($location)) {
            $point = ['lat' => (float) $location, 'lon' => (float) $longitude];
        }

        return $this->push(new GeoDistanceQuery($field, self::distance($distance), $point, $options));
    }

    /**
     * @param array<string, mixed> $boundingBox
     * @param array<string, mixed> $options
     */
    public function geoBoundingBox(string $field, array $boundingBox, array $options = []): static
    {
        return $this->push(new GeoBoundingBoxQuery($field, $boundingBox, $options));
    }

    /**
     * @param array<array-key, mixed> $points
     * @param array<string, mixed> $options
     */
    public function geoPolygon(string $field, array $points, array $options = []): static
    {
        return $this->push(new GeoPolygonQuery($field, $points, $options));
    }

    /* --------------------------------------------------------------------
     |  Bool query
     * ----------------------------------------------------------------- */

    public function bool(Closure $callback): static
    {
        return $this->push($this->compileClosure($callback));
    }

    /**
     * @param DslInterface|Closure|array<string, mixed> $clause
     */
    public function must(DslInterface|Closure|array $clause): static
    {
        $this->must[] = $this->normalizeClause($clause);

        return $this;
    }

    /**
     * @param DslInterface|Closure|array<string, mixed> $clause
     */
    public function mustNot(DslInterface|Closure|array $clause): static
    {
        $this->mustNot[] = $this->normalizeClause($clause);

        return $this;
    }

    /**
     * @param DslInterface|Closure|array<string, mixed> $clause
     */
    public function should(DslInterface|Closure|array $clause): static
    {
        $this->should[] = $this->normalizeClause($clause);

        return $this;
    }

    /**
     * @param DslInterface|Closure|array<string, mixed> $clause
     */
    public function filter(DslInterface|Closure|array $clause): static
    {
        $this->filter[] = $this->normalizeClause($clause);

        return $this;
    }

    public function minimumShouldMatch(int|string $value): static
    {
        $this->minimumShouldMatch = $value;

        return $this;
    }

    /* --------------------------------------------------------------------
     |  Raw DSL
     * ----------------------------------------------------------------- */

    public function rawQuery(array $query): static
    {
        $this->rawQueries[] = $query;

        return $this;
    }

    public function raw(array $body): static
    {
        $this->rawBody = array_replace($this->rawBody, $body);

        return $this;
    }

    /* --------------------------------------------------------------------
     |  Sorting
     * ----------------------------------------------------------------- */

    public function orderBy(array|string $field, string $direction = 'asc'): static
    {
        $this->sorts->add($field, $direction);

        return $this;
    }

    public function orderByAsc(string $field): static
    {
        return $this->orderBy($field, 'asc');
    }

    public function orderByDesc(string $field): static
    {
        return $this->orderBy($field, 'desc');
    }

    public function sort(string|array $field, string $direction = 'asc', array $options = []): static
    {
        $this->sorts->add($field, $direction, $options);

        return $this;
    }

    /* --------------------------------------------------------------------
     |  Source filtering
     * ----------------------------------------------------------------- */

    public function select(array|string $fields): static
    {
        $this->sourceIncludes = array_values(array_map('strval', Arr::wrap($fields)));

        return $this;
    }

    public function addSelect(array|string $fields): static
    {
        $fields = array_values(array_map('strval', Arr::wrap($fields)));
        $this->sourceIncludes = array_values(array_unique(
            array_merge($this->sourceIncludes ?? [], $fields),
        ));

        return $this;
    }

    public function selectExcept(array|string $fields): static
    {
        $fields = array_values(array_map('strval', Arr::wrap($fields)));
        $this->sourceExcludes = array_values(array_unique(array_merge($this->sourceExcludes, $fields)));

        return $this;
    }

    /* --------------------------------------------------------------------
     |  Highlight
     * ----------------------------------------------------------------- */

    public function highlight(array|string $fields, array $options = []): static
    {
        $this->highlight->add($fields, $options);

        return $this;
    }

    /* --------------------------------------------------------------------
     |  Aggregations
     * ----------------------------------------------------------------- */

    /**
     * @param AggregationInterface|Closure|array<string, mixed> $aggregation
     */
    public function aggregate(string $name, AggregationInterface|Closure|array $aggregation): static
    {
        if ($aggregation instanceof Closure) {
            $temporary = new AggregationBuilder();
            $aggregation($temporary);
            $first = $temporary->first();

            if ($first === null) {
                throw new InvalidQueryException(sprintf('The aggregation closure [%s] did not register any aggregation.', $name));
            }

            $sub = [];

            foreach ($temporary->entries() as $index => $entry) {
                if ($index === 0) {
                    continue;
                }

                $body = $entry['body'];

                if ($entry['sub'] !== []) {
                    $body['aggs'] = $entry['sub'];
                }

                $sub[$entry['name']] = $body;
            }

            $this->aggregations->add($name, $sub === [] ? $first['body'] : array_replace($first['body'], ['aggs' => $sub]));

            return $this;
        }

        $this->aggregations->add($name, $aggregation);

        return $this;
    }

    public function termsAgg(string $name, string $field, array $options = []): static
    {
        $this->aggregations->terms($name, $field, $options);

        return $this;
    }

    public function statsAgg(string $name, string $field, array $options = []): static
    {
        $this->aggregations->stats($name, $field, $options);

        return $this;
    }

    public function avgAgg(string $name, string $field, array $options = []): static
    {
        $this->aggregations->avg($name, $field, $options);

        return $this;
    }

    public function sumAgg(string $name, string $field, array $options = []): static
    {
        $this->aggregations->sum($name, $field, $options);

        return $this;
    }

    public function minAgg(string $name, string $field, array $options = []): static
    {
        $this->aggregations->min($name, $field, $options);

        return $this;
    }

    public function maxAgg(string $name, string $field, array $options = []): static
    {
        $this->aggregations->max($name, $field, $options);

        return $this;
    }

    public function cardinalityAgg(string $name, string $field, array $options = []): static
    {
        $this->aggregations->cardinality($name, $field, $options);

        return $this;
    }

    public function dateHistogramAgg(string $name, string $field, string $interval, array $options = []): static
    {
        $this->aggregations->dateHistogram($name, $field, $interval, $options);

        return $this;
    }

    public function histogramAgg(string $name, string $field, int|float $interval, array $options = []): static
    {
        $this->aggregations->histogram($name, $field, $interval, $options);

        return $this;
    }

    public function rangeAgg(string $name, string $field, array $ranges, array $options = []): static
    {
        $this->aggregations->range($name, $field, $ranges, $options);

        return $this;
    }

    public function dateRangeAgg(string $name, string $field, array $ranges, array $options = []): static
    {
        $this->aggregations->dateRange($name, $field, $ranges, $options);

        return $this;
    }

    public function filtersAgg(string $name, array $filters, array $options = []): static
    {
        $this->aggregations->filters($name, $filters, $options);

        return $this;
    }

    public function nestedAgg(string $name, string $path, array $options = []): static
    {
        $this->aggregations->nested($name, $path, $options);

        return $this;
    }

    public function reverseNestedAgg(string $name, string $path = '', array $options = []): static
    {
        $this->aggregations->reverseNested($name, $path, $options);

        return $this;
    }

    public function topHitsAgg(string $name, int $size = 3, array $options = []): static
    {
        $this->aggregations->topHits($name, $size, $options);

        return $this;
    }

    public function percentilesAgg(string $name, string $field, array $percents = [], array $options = []): static
    {
        $this->aggregations->percentiles($name, $field, $percents, $options);

        return $this;
    }

    public function significantTermsAgg(string $name, string $field, array $options = []): static
    {
        $this->aggregations->significantTerms($name, $field, $options);

        return $this;
    }

    /**
     * @param DslInterface|Closure|array<string, mixed> $query
     */
    public function filterAgg(string $name, DslInterface|Closure|array $query): static
    {
        $this->aggregations->filter($name, $this->normalizeClause($query));

        return $this;
    }

    /* --------------------------------------------------------------------
     |  Suggest
     * ----------------------------------------------------------------- */

    /**
     * Register a suggest clause (term / phrase / completion).
     *
     * @param array<string, mixed> $suggest
     */
    public function suggest(array $suggest): static
    {
        $this->suggest = array_replace($this->suggest, $suggest);

        return $this;
    }

    /* --------------------------------------------------------------------
     |  Window, deep pagination, PIT and scroll
     * ----------------------------------------------------------------- */

    public function limit(int $limit): static
    {
        $this->size = $limit;

        return $this;
    }

    public function offset(int $offset): static
    {
        $this->from = max(0, $offset);

        return $this;
    }

    public function searchAfter(array $values): static
    {
        $this->searchAfter = $values;

        return $this;
    }

    public function pit(string $pitId, string $keepAlive = '1m'): static
    {
        $this->pitId = $pitId;
        $this->pitKeepAlive = $keepAlive;

        return $this;
    }

    public function scroll(int $size, string $keepAlive = '1m'): static
    {
        $this->scrollSize = $size;
        $this->scrollKeepAlive = $keepAlive;
        $this->size = $size;

        return $this;
    }

    /* --------------------------------------------------------------------
     |  Soft deletes, scopes, caching
     * ----------------------------------------------------------------- */

    public function withTrashed(): static
    {
        $this->trashed = true;

        return $this;
    }

    public function onlyTrashed(): static
    {
        $this->trashed = false;

        return $this;
    }

    public function withScope(Scope|string $scope): static
    {
        $this->applyScope($scope);

        return $this;
    }

    public function cache(int $ttl, ?string $key = null): static
    {
        $this->cacheTtl = $ttl;
        $this->cacheKey = $key;

        return $this;
    }

    /* --------------------------------------------------------------------
     |  Relations
     * ----------------------------------------------------------------- */

    /**
     * Eager load relations in a single batch query (avoids N+1).
     *
     * @param array<array-key, string>|string $relations
     */
    public function with(array|string $relations): static
    {
        foreach (Arr::wrap($relations) as $relation) {
            $this->eagerLoads[] = (string) $relation;
        }

        return $this;
    }

    /* --------------------------------------------------------------------
     |  Execution
     * ----------------------------------------------------------------- */

    public function get(): ModelCollection
    {
        return $this->search()->hits();
    }

    public function search(): SearchResult
    {
        $cached = $this->readFromCache();

        if ($cached !== null) {
            $response = $cached;
        } else {
            $response = $this->connectionInstance()->request('search', $this->requestParams());

            $this->writeToCache($response);
        }

        $hits = $this->hydrate($response);
        $result = SearchResult::fromResponse($response, $hits, $this->pitId);
        $hits->setResult($result);

        $this->eagerLoad($result->hits());

        return $result;
    }

    public function first(): ?Model
    {
        $clone = clone $this;
        $clone->size = 1;
        $clone->from = 0;
        $clone->eagerLoads = $this->eagerLoads;

        $first = $clone->get()->first();

        return $first instanceof Model ? $first : null;
    }

    public function count(): int
    {
        $response = $this->connectionInstance()->request('count', [
            'index' => $this->getIndex(),
            'body' => ['query' => $this->queryClause()],
        ]);

        $count = $response['count'] ?? 0;

        return is_int($count) ? $count : (int) $count;
    }

    public function each(callable $callback, int $chunkSize = 1000): bool
    {
        if ($this->searchAfter !== [] || $this->pitId !== null) {
            return $this->eachWithSearchAfter($callback, $chunkSize);
        }

        return $this->eachWithScroll($callback, $chunkSize);
    }

    /**
     * Length aware pagination.
     */
    public function paginate(?int $perPage = null, ?int $page = null): LengthAwarePaginator
    {
        $perPage ??= $this->defaultPageSize();
        $page = max(1, $page ?? 1);

        $clone = clone $this;
        $clone->from = ($page - 1) * $perPage;
        $clone->size = $perPage;
        $clone->trackTotalHits = true;

        $result = $clone->search();

        return new LengthAwarePaginator($result->hits(), $result->total(), $perPage, $page);
    }

    /**
     * Simple (next/previous) pagination: only knows whether more pages exist.
     */
    public function simplePaginate(?int $perPage = null, ?int $page = null): Paginator
    {
        $perPage ??= $this->defaultPageSize();
        $page = max(1, $page ?? 1);

        $clone = clone $this;
        $clone->from = ($page - 1) * $perPage;
        $clone->size = $perPage + 1;

        $items = $clone->get()->values();
        $hasMore = $items->count() > $perPage;

        if ($hasMore) {
            $items = new ModelCollection(array_slice($items->all(), 0, $perPage));
        }

        return new Paginator($items, $perPage, $page, $hasMore);
    }

    /**
     * Cursor pagination built on `search_after` (deep pagination safe).
     *
     * @param array<int|string, mixed>|null $cursor
     */
    public function cursorPaginate(?int $perPage = null, ?array $cursor = null): CursorPaginator
    {
        $perPage ??= $this->defaultPageSize();

        $clone = clone $this;
        $clone->sorts = clone $this->sorts;
        $clone->sorts->ensureTieBreaker($this->model->getKeyName());
        $clone->size = $perPage + 1;

        if ($cursor !== null && $cursor !== []) {
            $clone->searchAfter = $cursor;
        }

        $result = $clone->search();
        $items = $result->hits()->values();
        $hasMore = $items->count() > $perPage;
        $nextCursor = null;

        if ($hasMore) {
            // The look-ahead (perPage + 1) document is only fetched to detect
            // whether a next page exists: the cursor must point at the LAST
            // RETURNED document, otherwise the next page would skip it.
            $documents = $result->raw['hits']['hits'] ?? [];
            $documents = is_array($documents) ? $documents : [];
            $cursor = SearchResult::cursorFrom(array_slice($documents, 0, $perPage));
            $nextCursor = $cursor === [] ? null : $cursor;

            $items = new ModelCollection(array_slice($items->all(), 0, $perPage));
        }

        return new CursorPaginator($items, $perPage, $nextCursor, $hasMore);
    }

    /* --------------------------------------------------------------------
     |  Debugging
     * ----------------------------------------------------------------- */

    /**
     * @return array<string, mixed>
     */
    public function toDsl(): array
    {
        $body = [
            'query' => $this->queryClause(),
        ];

        if (!$this->aggregations->isEmpty()) {
            $body['aggs'] = $this->aggregations->toArray();
        }

        if (!$this->sorts->isEmpty()) {
            $body['sort'] = $this->sorts->toArray();
        }

        $source = $this->sourceNode();

        if ($source !== null) {
            $body['_source'] = $source;
        }

        if (!$this->highlight->isEmpty()) {
            $body['highlight'] = $this->highlight->toArray();
        }

        if ($this->suggest !== []) {
            $body['suggest'] = $this->suggest;
        }

        $size = $this->size ?? $this->defaultPageSize();
        $body['size'] = $size;

        if ($this->from > 0) {
            $body['from'] = $this->from;
        }

        if ($this->searchAfter !== []) {
            $body['search_after'] = $this->searchAfter;
        }

        if ($this->pitId !== null) {
            $body['pit'] = ['id' => $this->pitId, 'keep_alive' => $this->pitKeepAlive];
        }

        if ($this->trackTotalHits && $this->scrollSize === null) {
            $body['track_total_hits'] = true;
        }

        if ($this->rawBody !== []) {
            $body = array_replace($body, $this->rawBody);
        }

        return $body;
    }

    public function toJson(int $options = 0): string
    {
        return json_encode($this->toDsl(), $options | JSON_THROW_ON_ERROR);
    }

    public function dump(): static
    {
        $this->print($this->toDsl());

        return $this;
    }

    public function dd(): never
    {
        $this->dump();

        exit(1);
    }

    /* --------------------------------------------------------------------
     |  Internals
     * ----------------------------------------------------------------- */

    /**
     * Forward local scopes (`scopeActive`, ...) and aggregation shorthands.
     *
     * @param array<int, mixed> $arguments
     */
    public function __call(string $method, array $arguments): mixed
    {
        $scope = 'scope' . ucfirst($method);

        if (method_exists($this->model, $scope)) {
            $this->model->{$scope}($this, ...$arguments);

            return $this;
        }

        if (method_exists($this->aggregations, $method)) {
            $this->aggregations->{$method}(...$arguments);

            return $this;
        }

        throw new InvalidQueryException(sprintf('Call to undefined query method [%s::%s()].', self::class, $method));
    }

    /**
     * @param array<string, mixed>|string|Closure $column
     */
    private function addWhere(array|string|Closure $column, mixed $operator, mixed $value, bool $or): void
    {
        if ($column instanceof Closure) {
            $clause = $this->compileClosure($column);

            $or ? $this->pushOr($clause) : $this->push($clause);

            return;
        }

        if (is_array($column)) {
            foreach ($column as $field => $fieldValue) {
                $this->addCompare((string) $field, '=', $fieldValue, $or);
            }

            return;
        }

        $this->addCompare($column, (string) ($operator ?? '='), $value, $or);
    }

    private function addCompare(string $column, string $operator, mixed $value, bool $or): void
    {
        $clause = self::compare($column, $operator, $value);

        $or ? $this->pushOr($clause) : $this->push($clause);
    }

    /**
     * Translate an Eloquent comparison operator into Elasticsearch DSL.
     *
     * @return array<string, mixed>
     */
    private static function compare(string $column, string $operator, mixed $value): array
    {
        return match (strtolower($operator)) {
            '=', '==' => (new TermQuery($column, $value))->toArray(),
            '!=', '<>' => (new BoolQuery(mustNot: [(new TermQuery($column, $value))->toArray()]))->toArray(),
            '>' => (new RangeQuery($column, ['gt' => $value]))->toArray(),
            '>=' => (new RangeQuery($column, ['gte' => $value]))->toArray(),
            '<' => (new RangeQuery($column, ['lt' => $value]))->toArray(),
            '<=' => (new RangeQuery($column, ['lte' => $value]))->toArray(),
            'like' => (new WildcardQuery($column, str_replace('%', '*', (string) $value)))->toArray(),
            'not like' => (new BoolQuery(mustNot: [(new WildcardQuery($column, str_replace('%', '*', (string) $value)))->toArray()]))->toArray(),
            'in' => (new TermsQuery($column, Arr::wrap($value)))->toArray(),
            'not in' => (new BoolQuery(mustNot: [(new TermsQuery($column, Arr::wrap($value)))->toArray()]))->toArray(),
            default => throw new InvalidQueryException(sprintf('Unsupported where operator [%s].', $operator)),
        };
    }

    /**
     * Append a clause to the current (AND) group.
     *
     * @param DslInterface|array<array-key, mixed> $clause
     */
    private function push(DslInterface|array $clause): static
    {
        $normalized = Dsl::clause($clause);
        $index = count($this->groups) - 1;
        $this->groups[$index][] = $normalized;

        return $this;
    }

    /**
     * Start a new group (OR with the previous ones).
     *
     * @param DslInterface|array<array-key, mixed> $clause
     */
    private function pushOr(DslInterface|array $clause): static
    {
        $this->groups[] = [Dsl::clause($clause)];

        return $this;
    }

    /**
     * @param DslInterface|Closure|array<array-key, mixed> $clause
     * @return array<string, mixed>
     */
    private function normalizeClause(DslInterface|Closure|array $clause): array
    {
        if ($clause instanceof Closure) {
            return $this->compileClosure($clause);
        }

        return Dsl::clause($clause);
    }

    /**
     * Run a closure against a nested builder and compile its query clause.
     *
     * @param Closure(Builder): void $callback
     * @return array<string, mixed>
     */
    private function compileClosure(Closure $callback): array
    {
        $nested = new self($this->model, $this->connections);
        $callback($nested);

        return $nested->queryClause();
    }

    /**
     * Build the final bool query from the accumulated state.
     *
     * @return array<string, mixed>
     */
    private function queryClause(): array
    {
        $must = $this->must;
        $mustNot = $this->mustNot;
        $should = $this->should;
        $filter = $this->filter;
        $minimumShouldMatch = $this->minimumShouldMatch;

        foreach ($this->rawQueries as $query) {
            $must[] = $query;
        }

        $groups = array_values(array_filter(
            $this->groups,
            static fn (array $group): bool => $group !== [],
        ));

        if (count($groups) <= 1) {
            foreach ($groups[0] ?? [] as $clause) {
                $must[] = $clause;
            }
        } else {
            foreach ($groups as $group) {
                $should[] = ['bool' => ['must' => $group]];
            }

            $minimumShouldMatch ??= 1;
        }

        $softDelete = $this->softDeleteClause();

        if ($softDelete !== null) {
            $filter[] = $softDelete;
        }

        if ($must === [] && $mustNot === [] && $should === [] && $filter === []) {
            return ['match_all' => (object) []];
        }

        $bool = [];

        if ($must !== []) {
            $bool['must'] = $must;
        }

        if ($mustNot !== []) {
            $bool['must_not'] = $mustNot;
        }

        if ($should !== []) {
            $bool['should'] = $should;
        }

        if ($filter !== []) {
            $bool['filter'] = $filter;
        }

        if ($minimumShouldMatch !== null) {
            $bool['minimum_should_match'] = $minimumShouldMatch;
        }

        return ['bool' => $bool];
    }

    /**
     * The soft delete filter, when the model supports soft deletes.
     *
     * @return array<string, mixed>|null
     */
    private function softDeleteClause(): ?array
    {
        if (!$this->model->usesSoftDeletes()) {
            return null;
        }

        $column = $this->model->getDeletedAtColumn();

        if ($this->trashed === true) {
            return null;
        }

        if ($this->trashed === false) {
            return (new ExistsQuery($column))->toArray();
        }

        return (new BoolQuery(mustNot: [(new ExistsQuery($column))->toArray()]))->toArray();
    }

    /**
     * @return array<string, mixed>|null
     */
    private function sourceNode(): array|null
    {
        if ($this->sourceIncludes === null && $this->sourceExcludes === []) {
            return null;
        }

        if ($this->sourceExcludes !== [] && $this->sourceIncludes === null) {
            return ['excludes' => $this->sourceExcludes];
        }

        $node = [];

        if ($this->sourceIncludes !== null) {
            $node['includes'] = $this->sourceIncludes;
        }

        if ($this->sourceExcludes !== []) {
            $node['excludes'] = $this->sourceExcludes;
        }

        return $node;
    }

    /**
     * @param array<string, mixed> $response
     */
    private function hydrate(array $response): ModelCollection
    {
        $hitsNode = $response['hits'] ?? [];
        $hitsNode = is_array($hitsNode) ? $hitsNode : [];
        $documents = $hitsNode['hits'] ?? [];
        $documents = is_array($documents) ? $documents : [];

        $models = [];

        foreach ($documents as $hit) {
            if (!is_array($hit)) {
                continue;
            }

            /** @var array<string, mixed> $hit */
            $models[] = $this->model->newFromHit($hit);
        }

        return new ModelCollection($models);
    }

    private function eagerLoad(ModelCollection $models): void
    {
        if ($this->eagerLoads === [] || $models->isEmpty()) {
            return;
        }

        $all = array_values($models->all());
        $prototype = $all[0];

        foreach ($this->eagerLoads as $name) {
            $segments = explode('.', $name, 2);
            $relationName = $segments[0];
            $nested = $segments[1] ?? '';

            $relation = $prototype->resolveRelation($relationName);
            $relation->setRelationName($relationName);

            if ($nested !== '') {
                $relation->getQuery()->with($nested);
            }

            $relation->addEagerConstraints($all);
            $relation->match($all, $relation->getEagerResults());
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function requestParams(): array
    {
        $params = [
            'body' => $this->toDsl(),
        ];

        if ($this->pitId === null) {
            $params['index'] = $this->getIndex();
        }

        if ($this->scrollSize !== null) {
            $params['scroll'] = $this->scrollKeepAlive;
        }

        return $params;
    }

    public function connectionInstance(): ConnectionInterface
    {
        $name = $this->connectionName ?? $this->model->getConnectionName();
        $name = $name === '' ? null : $name;

        return $this->connections->connection($name);
    }

    private function defaultPageSize(): int
    {
        $size = $this->connectionInstance()->getConfigValue('pagination.default_size', 15);

        return is_int($size) && $size > 0 ? $size : 15;
    }

    private function applyScope(Scope|string $scope): void
    {
        if ($scope instanceof Scope) {
            $scope->apply($this);

            return;
        }

        $method = 'scope' . ucfirst($scope);

        if (method_exists($this->model, $method)) {
            $this->model->{$method}($this);

            return;
        }

        throw new InvalidQueryException(sprintf('Unknown scope [%s].', $scope));
    }

    /* --------------------------------------------------------------------
     |  Scroll / search_after iteration
     * ----------------------------------------------------------------- */

    /**
     * @param callable(ModelInterface): mixed $callback
     */
    private function eachWithScroll(callable $callback, int $chunkSize): bool
    {
        $chunkSize = max(1, $chunkSize);
        $params = $this->requestParams();
        $params['body']['size'] = $chunkSize;
        unset($params['body']['from']);
        $params['scroll'] = $this->scrollKeepAlive;

        $response = $this->connectionInstance()->request('search', $params);
        $scrollId = is_string($response['_scroll_id'] ?? null) ? $response['_scroll_id'] : null;

        try {
            while (true) {
                $hits = $this->hydrate($response);

                if ($hits->isEmpty()) {
                    break;
                }

                if ($this->iterate($hits, $callback) === false) {
                    return false;
                }

                if ($scrollId === null) {
                    break;
                }

                $response = $this->connectionInstance()->request('scroll', [
                    'scroll_id' => $scrollId,
                    'scroll' => $this->scrollKeepAlive,
                ]);

                $scrollId = is_string($response['_scroll_id'] ?? null) ? $response['_scroll_id'] : $scrollId;
            }
        } finally {
            if ($scrollId !== null) {
                $this->connectionInstance()->request('clearScroll', ['scroll_id' => [$scrollId]]);
            }
        }

        return true;
    }

    /**
     * @param callable(ModelInterface): mixed $callback
     */
    private function eachWithSearchAfter(callable $callback, int $chunkSize): bool
    {
        $chunkSize = max(1, $chunkSize);

        $clone = clone $this;
        $clone->sorts = clone $this->sorts;
        $clone->sorts->ensureTieBreaker($this->model->getKeyName());
        $clone->size = $chunkSize;
        $clone->from = 0;

        $openedPit = null;

        if ($clone->pitId === null) {
            $response = $this->connectionInstance()->request('openPointInTime', [
                'index' => $this->getIndex(),
                'keep_alive' => $this->pitKeepAlive,
            ]);

            $openedPit = is_string($response['id'] ?? null) ? $response['id'] : null;
            $clone->pitId = $openedPit;
        }

        $after = $this->searchAfter;

        try {
            while (true) {
                if ($after !== []) {
                    $clone->searchAfter = $after;
                }

                $result = $clone->search();
                $hits = $result->hits();

                if ($hits->isEmpty()) {
                    break;
                }

                if ($this->iterate($hits, $callback) === false) {
                    return false;
                }

                if ($hits->count() < $chunkSize) {
                    break;
                }

                $after = $result->searchAfter();

                if ($after === []) {
                    break;
                }
            }
        } finally {
            if ($openedPit !== null) {
                $this->connectionInstance()->request('closePointInTime', ['body' => ['id' => $openedPit]]);
            }
        }

        return true;
    }

    /**
     * @param callable(ModelInterface): mixed $callback
     */
    private function iterate(ModelCollection $hits, callable $callback): bool
    {
        foreach ($hits as $model) {
            if ($callback($model) === false) {
                return false;
            }
        }

        return true;
    }

    /* --------------------------------------------------------------------
     |  Query cache
     * ----------------------------------------------------------------- */

    private function cacheStore(): ?CacheStoreInterface
    {
        if ($this->cacheTtl === null) {
            return null;
        }

        if ($this->cacheStore instanceof CacheStoreInterface) {
            return $this->cacheStore;
        }

        $driver = $this->connectionInstance()->getConfigValue('cache.driver');

        if (is_string($driver) && class_exists($driver) && is_subclass_of($driver, CacheStoreInterface::class)) {
            /** @var CacheStoreInterface $store */
            $store = new $driver();
            $this->cacheStore = $store;

            return $store;
        }

        return null;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function readFromCache(): ?array
    {
        $store = $this->cacheStore();

        if ($store === null) {
            return null;
        }

        $key = $this->resolveCacheKey();
        $cached = $store->get($key);

        /** @var array<string, mixed>|null $cached */
        return is_array($cached) ? $cached : null;
    }

    /**
     * @param array<string, mixed> $response
     */
    private function writeToCache(array $response): void
    {
        $store = $this->cacheStore();

        if ($store === null || !$this->connectionInstance()->getConfigValue('cache.enabled', false)) {
            return;
        }

        $store->put($this->resolveCacheKey(), $response, max(1, (int) $this->cacheTtl));
    }

    private function resolveCacheKey(): string
    {
        $prefix = (string) $this->connectionInstance()->getConfigValue('cache.prefix', 'es_orm:');

        if ($this->cacheKey !== null) {
            return $prefix . $this->cacheKey;
        }

        return $prefix . md5($this->getIndex() . '|' . $this->toJson());
    }

    /**
     * @param array<string, mixed> $dsl
     */
    private function print(array $dsl): void
    {
        $json = json_encode($dsl, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        if (class_exists(\Symfony\Component\VarDumper\VarDumper::class)) {
            \Symfony\Component\VarDumper\VarDumper::dump($dsl);

            return;
        }

        echo ($json === false ? '' : $json) . PHP_EOL;
    }

    private static function distance(int|float|string $distance): string
    {
        if (is_string($distance)) {
            return $distance;
        }

        return $distance . 'km';
    }
}
