<?php

declare(strict_types=1);

namespace EsOrm\Mapping;

use EsOrm\Contracts\MappingInterface;

/**
 * A fluent, framework agnostic Elasticsearch index mapping.
 *
 * ```php
 * Mapping::create('articles')
 *     ->text('title')->field('keyword', Field::keyword())
 *     ->keyword('status')
 *     ->date('created_at')
 *     ->double('price');
 * ```
 *
 * The mapping is pure data: it never performs HTTP requests. Hand it to the
 * {@see \EsOrm\Index\IndexManager} to actually create or update the index.
 */
final class Mapping implements MappingInterface
{
    /**
     * Fields declared through the fluent API.
     *
     * @var array<string, Field>
     */
    private array $properties = [];

    /**
     * Properties declared as raw arrays (from `Model::mapping()` or
     * {@see Mapping::rawProperty()}).
     *
     * @var array<string, mixed>
     */
    private array $rawProperties = [];

    /**
     * @param array<string, mixed> $settings
     */
    private function __construct(private string $index, private array $settings = [])
    {
    }

    /**
     * @param array<string, mixed> $settings
     */
    public static function create(string $index, array $settings = []): self
    {
        return new self($index, $settings);
    }

    /**
     * Build a mapping from a raw array as returned by `Model::mapping()`.
     *
     * Accepts either a full body (`['properties' => [...], 'settings' => [...]]`),
     * a mappings body (`['properties' => [...]]`) or a bare properties array.
     *
     * @param array<string, mixed> $mapping
     */
    public static function fromArray(string $index, array $mapping): self
    {
        $settings = $mapping['settings'] ?? null;

        if (array_key_exists('properties', $mapping)) {
            $properties = $mapping['properties'];
        } elseif (array_key_exists('settings', $mapping)) {
            $properties = [];
        } else {
            $properties = $mapping;
        }

        $self = new self($index, is_array($settings) ? $settings : []);

        if (is_array($properties)) {
            foreach ($properties as $name => $definition) {
                if (is_array($definition)) {
                    $self->rawProperty((string) $name, $definition);
                }
            }
        }

        return $self;
    }

    /* --------------------------------------------------------------------
     |  Field definition
     * ----------------------------------------------------------------- */

    /**
     * Add a field of an arbitrary type.
     *
     * @param array<string, mixed> $options
     */
    public function fieldOf(string $type, string $name, array $options = []): self
    {
        return $this->field($name, Field::of($type, $options));
    }

    /**
     * Add a field.
     */
    public function field(string $name, Field $field): self
    {
        $this->properties[$name] = $field;

        return $this;
    }

    /**
     * Add a raw property definition (any Elasticsearch property array).
     *
     * @param array<string, mixed> $definition
     */
    public function rawProperty(string $name, array $definition): self
    {
        $this->rawProperties[$name] = $definition;

        return $this;
    }

    /** @param array<string, mixed> $options */
    public function text(string $name, array $options = []): self
    {
        return $this->field($name, Field::text($options));
    }

    /** @param array<string, mixed> $options */
    public function keyword(string $name, array $options = []): self
    {
        return $this->field($name, Field::keyword($options));
    }

    /** @param array<string, mixed> $options */
    public function long(string $name, array $options = []): self
    {
        return $this->field($name, Field::long($options));
    }

    /** @param array<string, mixed> $options */
    public function integer(string $name, array $options = []): self
    {
        return $this->field($name, Field::integer($options));
    }

    /** @param array<string, mixed> $options */
    public function short(string $name, array $options = []): self
    {
        return $this->field($name, Field::short($options));
    }

    /** @param array<string, mixed> $options */
    public function byte(string $name, array $options = []): self
    {
        return $this->field($name, Field::byte($options));
    }

    /** @param array<string, mixed> $options */
    public function double(string $name, array $options = []): self
    {
        return $this->field($name, Field::double($options));
    }

    /** @param array<string, mixed> $options */
    public function float(string $name, array $options = []): self
    {
        return $this->field($name, Field::float($options));
    }

    /** @param array<string, mixed> $options */
    public function boolean(string $name, array $options = []): self
    {
        return $this->field($name, Field::boolean($options));
    }

    /** @param array<string, mixed> $options */
    public function date(string $name, array $options = []): self
    {
        return $this->field($name, Field::date($options));
    }

    /** @param array<string, mixed> $options */
    public function object(string $name, array $options = []): self
    {
        return $this->field($name, Field::object($options));
    }

    /** @param array<string, mixed> $options */
    public function nested(string $name, array $options = []): self
    {
        return $this->field($name, Field::nested($options));
    }

    /** @param array<string, mixed> $options */
    public function geoPoint(string $name, array $options = []): self
    {
        return $this->field($name, Field::geoPoint($options));
    }

    /** @param array<string, mixed> $options */
    public function ip(string $name, array $options = []): self
    {
        return $this->field($name, Field::ip($options));
    }

    /** @param array<string, mixed> $options */
    public function completion(string $name, array $options = []): self
    {
        return $this->field($name, Field::completion($options));
    }

    /** @param array<string, mixed> $options */
    public function denseVector(string $name, array $options = []): self
    {
        return $this->field($name, Field::denseVector($options));
    }

    /* --------------------------------------------------------------------
     |  Settings
     * ----------------------------------------------------------------- */

    /**
     * Merge additional index settings into this mapping.
     *
     * @param array<string, mixed> $settings
     */
    public function withSettings(array $settings): self
    {
        $this->settings = array_replace($this->settings, $settings);

        return $this;
    }

    /* --------------------------------------------------------------------
     |  Inspection / serialization
     * ----------------------------------------------------------------- */

    public function getIndex(): string
    {
        return $this->index;
    }

    /**
     * @return array<string, mixed>
     */
    public function settings(): array
    {
        return $this->settings;
    }

    /**
     * The declared fields, keyed by name.
     *
     * @return array<string, Field>
     */
    public function properties(): array
    {
        return $this->properties;
    }

    public function hasProperties(): bool
    {
        return $this->properties !== [] || $this->rawProperties !== [];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $properties = $this->rawProperties;

        foreach ($this->properties as $name => $field) {
            $properties[$name] = $field->toArray();
        }

        return ['properties' => $properties];
    }

    /**
     * @return array<string, mixed>
     */
    public function toIndexBody(): array
    {
        $body = [];

        if ($this->settings !== []) {
            $body['settings'] = $this->settings;
        }

        $body['mappings'] = $this->toArray();

        return $body;
    }
}
