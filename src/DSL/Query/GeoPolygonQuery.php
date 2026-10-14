<?php

declare(strict_types=1);

namespace EsOrm\DSL\Query;

/**
 * Builds an Elasticsearch `geo_polygon` query, which matches documents whose
 * geo-point field falls inside the polygon defined by the supplied points.
 *
 * @see https://www.elastic.co/guide/en/elasticsearch/reference/current/query-dsl-geo-polygon-query.html
 */
final class GeoPolygonQuery extends Query
{
    /**
     * @param array<array-key, mixed> $points
     * @param array<string, mixed>    $options
     */
    public function __construct(
        public readonly string $field,
        public readonly array $points,
        public readonly array $options = [],
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'geo_polygon' => array_replace([
                $this->field => ['points' => array_values($this->points)],
            ], $this->options),
        ];
    }
}
