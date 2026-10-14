<?php

declare(strict_types=1);

namespace EsOrm\Contracts;

/**
 * A single Elasticsearch aggregation.
 *
 * Implementations only describe the *body* of the aggregation, e.g.
 * `['terms' => ['field' => 'category']]`. Naming and sub-aggregation nesting is
 * handled by {@see \EsOrm\Query\AggregationBuilder}.
 */
interface AggregationInterface extends DslInterface
{
}
