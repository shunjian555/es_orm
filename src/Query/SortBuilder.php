<?php

declare(strict_types=1);

namespace EsOrm\Query;

use EsOrm\Contracts\DslInterface;
use EsOrm\DSL\Sort\FieldSort;

/**
 * Collects the sort clauses of a search request.
 *
 * Three forms are supported, from the simplest to the full Elasticsearch DSL:
 *
 * ```php
 * $sorts->add('created_at', 'desc');
 * $sorts->add(['created_at' => 'desc', 'price' => 'asc']);
 * $sorts->add('price', 'asc', ['missing' => '_last', 'mode' => 'avg']);
 * ```
 */
final class SortBuilder
{
    /**
     * @var list<array<string, mixed>>
     */
    private array $sorts = [];

    /**
     * @param array<string, mixed>|DslInterface|string $field
     * @param array<string, mixed> $options
     */
    public function add(array|DslInterface|string $field, string $direction = 'asc', array $options = []): static
    {
        if ($field instanceof DslInterface) {
            $this->sorts[] = $field->toArray();

            return $this;
        }

        if (is_array($field)) {
            foreach ($field as $name => $value) {
                if ($value instanceof DslInterface) {
                    $this->sorts[] = $value->toArray();

                    continue;
                }

                if (is_int($name)) {
                    // A plain list of field names: `['created_at', 'price']`.
                    $this->sorts[] = [(string) $value => 'asc'];

                    continue;
                }

                $this->sorts[] = [$name => (string) $value];
            }

            return $this;
        }

        $this->sorts[] = (new FieldSort($field, $direction, $options))->toArray();

        return $this;
    }

    public function isEmpty(): bool
    {
        return $this->sorts === [];
    }

    /**
     * Whether the `_score` field is part of the sort.
     */
    public function hasScore(): bool
    {
        foreach ($this->sorts as $sort) {
            if (array_key_exists('_score', $sort)) {
                return true;
            }
        }

        return false;
    }

    /**
     * The sort fields, in order.
     *
     * @return list<string>
     */
    public function fields(): array
    {
        $fields = [];

        foreach ($this->sorts as $sort) {
            foreach (array_keys($sort) as $name) {
                $fields[] = $name;
            }
        }

        return $fields;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function all(): array
    {
        return $this->sorts;
    }

    /**
     * Compile the `sort` node of the request body.
     *
     * @return list<array<string, mixed>>
     */
    public function toArray(): array
    {
        return $this->sorts;
    }

    /**
     * Replace the sort clauses.
     *
     * @param list<array<string, mixed>> $sorts
     */
    public function replace(array $sorts): static
    {
        $this->sorts = $sorts;

        return $this;
    }

    /**
     * Ensure at least one sort exists so that `search_after` cursors stay
     * stable even when the caller did not request any ordering.
     */
    public function ensureTieBreaker(string $field = '_id'): static
    {
        if ($this->isEmpty()) {
            $this->sorts[] = [$field => 'asc'];
        }

        return $this;
    }
}
