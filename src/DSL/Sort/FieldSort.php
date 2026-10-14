<?php

declare(strict_types=1);

namespace EsOrm\DSL\Sort;

use EsOrm\Contracts\DslInterface;

/**
 * A single sort clause, including the full Elasticsearch sort DSL.
 *
 * The scalar form is used when no options are given (`['price' => 'asc']`),
 * otherwise the extended form is emitted (`['price' => ['order' => 'asc',
 * 'missing' => '_last']]`).
 */
final class FieldSort implements DslInterface
{
    /**
     * @param array<string, mixed> $options
     */
    public function __construct(
        public readonly string $field,
        public readonly string $direction = 'asc',
        public readonly array $options = [],
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        if ($this->options === []) {
            return [$this->field => $this->direction];
        }

        return [
            $this->field => array_replace(['order' => $this->direction], $this->options),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
