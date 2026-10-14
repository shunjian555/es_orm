<?php

declare(strict_types=1);

namespace EsOrm\DSL\Query;

/**
 * Builds an Elasticsearch `geo_distance` query, which matches documents whose
 * geo-point field lies within the given distance of an origin point.
 *
 * @see https://www.elastic.co/guide/en/elasticsearch/reference/current/query-dsl-geo-distance-query.html
 */
final class GeoDistanceQuery extends Query
{
    /**
     * @param array<array-key, mixed>|string $location
     * @param array<string, mixed>          $options
     */
    public function __construct(
        public readonly string $field,
        public readonly string $distance,
        public readonly array|string $location,
        public readonly array $options = [],
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'geo_distance' => array_replace([
                'distance' => $this->distance,
                $this->field => $this->location,
            ], $this->options),
        ];
    }
}
