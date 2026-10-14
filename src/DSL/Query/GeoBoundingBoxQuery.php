<?php

declare(strict_types=1);

namespace EsOrm\DSL\Query;

/**
 * Builds an Elasticsearch `geo_bounding_box` query, which matches documents
 * whose geo-point field falls inside a rectangle defined by two corners.
 *
 * @see https://www.elastic.co/guide/en/elasticsearch/reference/current/query-dsl-geo-bounding-box-query.html
 */
final class GeoBoundingBoxQuery extends Query
{
    /**
     * @param array<string, mixed> $boundingBox
     * @param array<string, mixed> $options
     */
    public function __construct(
        public readonly string $field,
        public readonly array $boundingBox,
        public readonly array $options = [],
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'geo_bounding_box' => array_replace([
                $this->field => $this->boundingBox,
            ], $this->options),
        ];
    }
}
