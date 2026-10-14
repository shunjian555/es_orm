<?php

declare(strict_types=1);

namespace EsOrm\Contracts;

use Closure;
use EsOrm\Scope\Scope;

/**
 * The public, fluent Query Builder contract.
 *
 * It intentionally mixes an Eloquent-like surface (`where`, `orderBy`,
 * `paginate`, ...) with native Elasticsearch DSL (`match`, `term`, `nested`,
 * `aggregate`, `rawQuery`, ...): the ORM must never hide what Elasticsearch can
 * do.
 *
 * @phpstan-type SortDirection 'asc'|'desc'
 * @phpstan-type WhereOperator string
 */
interface QueryBuilderInterface
{
    /**
     * The index (or alias) the query runs against.
     */
    public function getIndex(): string;

    /**
     * Override the index for this query only.
     */
    public function from(string $index): static;

    /**
     * Run this query on another connection.
     */
    public function connection(string $name): static;

    /**
     * Eloquent style where. Supports `where('age', '>', 18)`, `where('status',
     * 'active')`, `where(['a' => 'b'])` and nested closures.
     *
     * @param array<string, mixed>|string|Closure(QueryBuilderInterface): void $column
     */
    public function where(array|string|Closure $column, mixed $operator = null, mixed $value = null): static;

    /**
     * @param array<string, mixed>|string|Closure(QueryBuilderInterface): void $column
     */
    public function orWhere(array|string|Closure $column, mixed $operator = null, mixed $value = null): static;

    /**
     * @param array<array-key, mixed> $values
     */
    public function whereIn(string $column, array $values): static;

    /**
     * @param array<array-key, mixed> $values
     */
    public function whereNotIn(string $column, array $values): static;

    public function whereNull(string $column): static;

    public function whereNotNull(string $column): static;

    /**
     * @param array{0: mixed, 1: mixed}|array<array-key, mixed> $values
     */
    public function whereBetween(string $column, array $values): static;

    /**
     * @param array{0: mixed, 1: mixed}|array<array-key, mixed> $values
     */
    public function whereNotBetween(string $column, array $values): static;

    public function whereExists(string $column): static;

    public function whereNotExists(string $column): static;

    /**
     * @param Closure(QueryBuilderInterface): void $callback
     */
    public function whereNested(Closure $callback): static;

    /**
     * @param Closure(QueryBuilderInterface): void $callback
     */
    public function orWhereNested(Closure $callback): static;

    /**
     * @param array<string, mixed> $options
     */
    public function match(string $field, string $query, array $options = []): static;

    /**
     * @param array<string, mixed> $options
     */
    public function matchPhrase(string $field, string $query, array $options = []): static;

    /**
     * @param array<array-key, string>|string $fields
     * @param array<string, mixed> $options
     */
    public function multiMatch(array|string $fields, string $query, array $options = []): static;

    /**
     * @param array<string, mixed> $options
     */
    public function term(string $field, mixed $value, array $options = []): static;

    /**
     * @param array<array-key, mixed> $values
     */
    public function terms(string $field, array $values): static;

    /**
     * @param array<string, mixed> $ranges
     */
    public function range(string $field, array $ranges): static;

    public function exists(string $field): static;

    /**
     * @param array<string, mixed> $options
     */
    public function prefix(string $field, string $value, array $options = []): static;

    /**
     * @param array<string, mixed> $options
     */
    public function wildcard(string $field, string $value, array $options = []): static;

    /**
     * @param array<string, mixed> $options
     */
    public function regexp(string $field, string $value, array $options = []): static;

    /**
     * @param array<string, mixed> $options
     */
    public function fuzzy(string $field, string $value, array $options = []): static;

    /**
     * @param array<array-key, mixed> $ids
     */
    public function ids(array $ids): static;

    /**
     * @param array<string, mixed> $options
     */
    public function queryString(string $query, array $options = []): static;

    /**
     * @param array<string, mixed> $options
     */
    public function simpleQueryString(string $query, array $options = []): static;

    /**
     * @param Closure(QueryBuilderInterface): void $callback
     * @param array<string, mixed> $options
     */
    public function nested(string $path, Closure $callback, array $options = []): static;

    /**
     * @param Closure(QueryBuilderInterface): void $callback
     */
    public function bool(Closure $callback): static;

    /**
     * @param DslInterface|Closure|array<string, mixed> $clause
     */
    public function must(DslInterface|Closure|array $clause): static;

    /**
     * @param DslInterface|Closure|array<string, mixed> $clause
     */
    public function mustNot(DslInterface|Closure|array $clause): static;

    /**
     * @param DslInterface|Closure|array<string, mixed> $clause
     */
    public function should(DslInterface|Closure|array $clause): static;

    /**
     * @param DslInterface|Closure|array<string, mixed> $clause
     */
    public function filter(DslInterface|Closure|array $clause): static;

    public function minimumShouldMatch(int|string $value): static;

    /**
     * Inject a raw Elasticsearch query fragment (advanced API).
     *
     * @param array<string, mixed> $query
     */
    public function rawQuery(array $query): static;

    /**
     * Merge a raw request body into the compiled DSL (advanced API).
     *
     * @param array<string, mixed> $body
     */
    public function raw(array $body): static;

    /**
     * @param array<string, SortDirection>|string $field
     */
    public function orderBy(array|string $field, string $direction = 'asc'): static;

    public function orderByAsc(string $field): static;

    public function orderByDesc(string $field): static;

    /**
     * Full Elasticsearch sort DSL.
     *
     * @param array<string, mixed>|string $field
     * @param array<string, mixed> $options
     */
    public function sort(string|array $field, string $direction = 'asc', array $options = []): static;

    /**
     * @param array<array-key, string>|string $fields
     */
    public function select(array|string $fields): static;

    /**
     * @param array<array-key, string>|string $fields
     */
    public function addSelect(array|string $fields): static;

    /**
     * @param array<array-key, string>|string $fields
     */
    public function selectExcept(array|string $fields): static;

    /**
     * @param array<array-key, string>|string $fields
     * @param array<string, mixed> $options
     */
    public function highlight(array|string $fields, array $options = []): static;

    /**
     * Register a named aggregation.
     *
     * @param AggregationInterface|Closure|array<string, mixed> $aggregation
     */
    public function aggregate(string $name, AggregationInterface|Closure|array $aggregation): static;

    /**
     * @param array<string, mixed> $options
     */
    public function termsAgg(string $name, string $field, array $options = []): static;

    /**
     * @param array<string, mixed> $options
     */
    public function statsAgg(string $name, string $field, array $options = []): static;

    /**
     * @param array<string, mixed> $options
     */
    public function avgAgg(string $name, string $field, array $options = []): static;

    /**
     * @param array<string, mixed> $options
     */
    public function sumAgg(string $name, string $field, array $options = []): static;

    /**
     * @param array<string, mixed> $options
     */
    public function minAgg(string $name, string $field, array $options = []): static;

    /**
     * @param array<string, mixed> $options
     */
    public function maxAgg(string $name, string $field, array $options = []): static;

    /**
     * @param array<string, mixed> $options
     */
    public function cardinalityAgg(string $name, string $field, array $options = []): static;

    /**
     * @param array<string, mixed> $options
     */
    public function dateHistogramAgg(string $name, string $field, string $interval, array $options = []): static;

    /**
     * @param array<string, mixed> $options
     */
    public function histogramAgg(string $name, string $field, int|float $interval, array $options = []): static;

    /**
     * @param array<array-key, mixed> $ranges
     * @param array<string, mixed> $options
     */
    public function rangeAgg(string $name, string $field, array $ranges, array $options = []): static;

    /**
     * @param array<array-key, mixed> $ranges
     * @param array<string, mixed> $options
     */
    public function dateRangeAgg(string $name, string $field, array $ranges, array $options = []): static;

    /**
     * @param array<array-key, mixed> $filters
     * @param array<string, mixed> $options
     */
    public function filtersAgg(string $name, array $filters, array $options = []): static;

    /**
     * @param array<string, mixed> $options
     */
    public function nestedAgg(string $name, string $path, array $options = []): static;

    /**
     * @param array<string, mixed> $options
     */
    public function reverseNestedAgg(string $name, string $path = '', array $options = []): static;

    /**
     * @param array<string, mixed> $options
     */
    public function topHitsAgg(string $name, int $size = 3, array $options = []): static;

    /**
     * @param array<array-key, float|int> $percents
     * @param array<string, mixed> $options
     */
    public function percentilesAgg(string $name, string $field, array $percents = [], array $options = []): static;

    /**
     * @param array<string, mixed> $options
     */
    public function significantTermsAgg(string $name, string $field, array $options = []): static;

    /**
     * @param DslInterface|Closure|array<string, mixed> $query
     */
    public function filterAgg(string $name, DslInterface|Closure|array $query): static;

    public function limit(int $limit): static;

    public function offset(int $offset): static;

    /**
     * Continue a search from a `search_after` cursor.
     *
     * @param array<int|string, mixed> $values
     */
    public function searchAfter(array $values): static;

    /**
     * Bind the query to a Point In Time.
     */
    public function pit(string $pitId, string $keepAlive = '1m'): static;

    /**
     * Enable scroll mode with the given page size.
     */
    public function scroll(int $size, string $keepAlive = '1m'): static;

    /**
     * Include soft deleted documents.
     */
    public function withTrashed(): static;

    /**
     * Only return soft deleted documents.
     */
    public function onlyTrashed(): static;

    /**
     * Apply a reusable constraint.
     */
    public function withScope(Scope|string $scope): static;

    /**
     * Cache the result of this query for the given number of seconds.
     */
    public function cache(int $ttl, ?string $key = null): static;

    /**
     * Execute the query and return the hydrated documents.
     *
     * @return CollectionInterface<array-key, object>
     */
    public function get(): CollectionInterface;

    /**
     * Execute the query and return the full result (hits + aggregations + meta).
     */
    public function search(): SearchResultInterface;

    /**
     * Return the first matching document.
     */
    public function first(): ?ModelInterface;

    /**
     * Count the matching documents.
     */
    public function count(): int;

    /**
     * Iterate over the whole result set, using scroll (or search_after when a
     * `searchAfter()`/`pit()` cursor is configured).
     *
     * @param callable(ModelInterface): mixed $callback
     */
    public function each(callable $callback, int $chunkSize = 1000): bool;

    /**
     * The compiled Elasticsearch request body.
     *
     * @return array<string, mixed>
     */
    public function toDsl(): array;

    public function toJson(int $options = 0): string;

    /**
     * Print the compiled DSL (development helper).
     */
    public function dump(): static;

    /**
     * Print the compiled DSL and stop the script (development helper).
     */
    public function dd(): never;
}
