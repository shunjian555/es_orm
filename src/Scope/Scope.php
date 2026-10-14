<?php

declare(strict_types=1);

namespace EsOrm\Scope;

use EsOrm\Contracts\QueryBuilderInterface;

/**
 * A reusable query constraint, applied either as a local scope through
 * `$query->withScope($scope)` or as a global scope registered on a model.
 *
 * Local `scopeXxx()` methods on a model are the Laravel-style alternative;
 * classes implementing this interface are the object oriented one and can be
 * reused across models.
 */
interface Scope
{
    /**
     * Apply the constraint to the given query.
     */
    public function apply(QueryBuilderInterface $builder): void;
}
