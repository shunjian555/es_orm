<?php

declare(strict_types=1);

namespace EsOrm\Model\Casts;

/**
 * Contract for value casters translating between raw Elasticsearch `_source`
 * values and native PHP values.
 */
interface CastInterface
{
    /**
     * Convert a raw Elasticsearch / stored value into a PHP value.
     *
     * The conversion runs from the storage representation read from `_source`
     * towards the hydrated PHP value returned to the caller.
     *
     * @param mixed $value The raw value read from the stored document.
     *
     * @return mixed The hydrated PHP value.
     */
    public function get(mixed $value): mixed;

    /**
     * Convert a PHP value into an Elasticsearch-storable value.
     *
     * The conversion runs from the PHP value supplied by the caller towards
     * the representation that Elasticsearch is able to persist.
     *
     * @param mixed $value The PHP value supplied by the caller.
     *
     * @return mixed The value prepared for storage in Elasticsearch.
     */
    public function set(mixed $value): mixed;
}
