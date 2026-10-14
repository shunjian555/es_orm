<?php

declare(strict_types=1);

namespace EsOrm\Query;

/**
 * Collects the highlight configuration of a search request.
 *
 * It supports both the simple list form (`['title', 'content']`) and the full
 * Elasticsearch per-field configuration
 * (`['title' => ['fragment_size' => 100]]`), plus the global options
 * (`pre_tags`, `post_tags`, `fragment_size`, `number_of_fragments`, `type`, ...).
 */
final class HighlightBuilder
{
    /**
     * Ordered map of field name => per-field options.
     *
     * @var array<string, array<string, mixed>|object>
     */
    private array $fields = [];

    /**
     * Global highlight options.
     *
     * @var array<string, mixed>
     */
    private array $options = [];

    /**
     * @param array<array-key, string>|string $fields
     * @param array<string, mixed> $options
     */
    public function add(array|string $fields, array $options = []): static
    {
        foreach (is_array($fields) ? $fields : [$fields] as $name => $value) {
            if (is_int($name)) {
                // `['title', 'content']` form.
                $this->fields[(string) $value] = (object) [];

                continue;
            }

            // `['title' => ['fragment_size' => 100]]` form.
            $fieldOptions = is_array($value) ? $value : [];
            $this->fields[(string) $name] = $fieldOptions === [] ? (object) [] : $fieldOptions;
        }

        if ($options !== []) {
            $this->options = array_replace($this->options, $options);
        }

        return $this;
    }

    /**
     * @param array<string, mixed> $options
     */
    public function options(array $options): static
    {
        $this->options = array_replace($this->options, $options);

        return $this;
    }

    public function isEmpty(): bool
    {
        return $this->fields === [] && $this->options === [];
    }

    /**
     * Compile the `highlight` node of the request body.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        if ($this->fields === [] && $this->options === []) {
            return [];
        }

        $compiled = $this->options;

        if ($this->fields !== []) {
            $compiled['fields'] = $this->fields;
        }

        return $compiled;
    }
}
