# Changelog

All notable changes to `php-es-orm/es-orm` will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added

- Core model layer: `Model`, `ModelCollection`, `SearchResult`, `Attribute`, casts, accessors/mutators, fillable/guarded.
- Fluent query builder with Elasticsearch Query DSL, Bool / Nested / Raw DSL support.
- DSL value objects for queries, aggregations, sorting and filters.
- Transport layer: `ConnectionManager`, `Connection`, official-client adapter and retry handling.
- Pagination: `paginate`, `simplePaginate`, `cursorPaginate` (search_after) and Point In Time.
- Aggregations, highlighting, suggesters, geo queries, scroll, bulk, multi-search and multi-get.
- Index, mapping, alias and reindex managers plus index migrations.
- Scopes, soft deletes, events, observers and relations (HasOne / HasMany / BelongsTo) with eager loading.
- Configurable multi-connection setup with basic auth, API key, bearer token, Cloud ID and TLS support.
- Query debugging (`toDsl`, `toJson`, `dump`, `dd`) and an optional, pluggable query cache.
- Laravel integration: service provider, facade, config publishing and Artisan commands.
