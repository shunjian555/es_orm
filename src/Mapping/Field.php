<?php

declare(strict_types=1);

namespace EsOrm\Mapping;

use EsOrm\Exceptions\MappingException;

/**
 * A single Elasticsearch mapping field.
 *
 * A field is a small, fluent value object: it holds a field `type` plus the raw
 * Elasticsearch property options. Nested object/nested properties and
 * multi-fields are expressed with {@see Field::property()} /
 * {@see Field::field()} and serialised recursively by {@see Field::toArray()}.
 */
final class Field
{
    /**
     * @param array<string, mixed> $options
     */
    private function __construct(private string $type, private array $options = [])
    {
        if ($type === '') {
            throw new MappingException('A mapping field type must not be empty.');
        }
    }

    /**
     * Build a field of an arbitrary type.
     *
     * @param array<string, mixed> $options
     */
    public static function of(string $type, array $options = []): self
    {
        return new self($type, $options);
    }

    /* --------------------------------------------------------------------
     |  Common type shortcuts
     * ----------------------------------------------------------------- */

    /** @param array<string, mixed> $options */
    public static function text(array $options = []): self
    {
        return new self('text', $options);
    }

    /** @param array<string, mixed> $options */
    public static function keyword(array $options = []): self
    {
        return new self('keyword', $options);
    }

    /** @param array<string, mixed> $options */
    public static function long(array $options = []): self
    {
        return new self('long', $options);
    }

    /** @param array<string, mixed> $options */
    public static function integer(array $options = []): self
    {
        return new self('integer', $options);
    }

    /** @param array<string, mixed> $options */
    public static function short(array $options = []): self
    {
        return new self('short', $options);
    }

    /** @param array<string, mixed> $options */
    public static function byte(array $options = []): self
    {
        return new self('byte', $options);
    }

    /** @param array<string, mixed> $options */
    public static function double(array $options = []): self
    {
        return new self('double', $options);
    }

    /** @param array<string, mixed> $options */
    public static function float(array $options = []): self
    {
        return new self('float', $options);
    }

    /** @param array<string, mixed> $options */
    public static function boolean(array $options = []): self
    {
        return new self('boolean', $options);
    }

    /** @param array<string, mixed> $options */
    public static function date(array $options = []): self
    {
        return new self('date', $options);
    }

    /** @param array<string, mixed> $options */
    public static function object(array $options = []): self
    {
        return new self('object', $options);
    }

    /** @param array<string, mixed> $options */
    public static function nested(array $options = []): self
    {
        return new self('nested', $options);
    }

    /** @param array<string, mixed> $options */
    public static function geoPoint(array $options = []): self
    {
        return new self('geo_point', $options);
    }

    /** @param array<string, mixed> $options */
    public static function geoShape(array $options = []): self
    {
        return new self('geo_shape', $options);
    }

    /** @param array<string, mixed> $options */
    public static function ip(array $options = []): self
    {
        return new self('ip', $options);
    }

    /** @param array<string, mixed> $options */
    public static function binary(array $options = []): self
    {
        return new self('binary', $options);
    }

    /** @param array<string, mixed> $options */
    public static function completion(array $options = []): self
    {
        return new self('completion', $options);
    }

    /** @param array<string, mixed> $options */
    public static function searchAsYouType(array $options = []): self
    {
        return new self('search_as_you_type', $options);
    }

    /** @param array<string, mixed> $options */
    public static function denseVector(array $options = []): self
    {
        return new self('dense_vector', $options);
    }

    /** @param array<string, mixed> $options */
    public static function flattened(array $options = []): self
    {
        return new self('flattened', $options);
    }

    /* --------------------------------------------------------------------
     |  Fluent configuration
     * ----------------------------------------------------------------- */

    /**
     * The analyzer used at index time (text fields).
     */
    public function analyzer(string $analyzer): self
    {
        $this->options['analyzer'] = $analyzer;

        return $this;
    }

    /**
     * The analyzer used at search time (text fields).
     */
    public function searchAnalyzer(string $analyzer): self
    {
        $this->options['search_analyzer'] = $analyzer;

        return $this;
    }

    /**
     * The normalizer used for keyword fields.
     */
    public function normalizer(string $normalizer): self
    {
        $this->options['normalizer'] = $normalizer;

        return $this;
    }

    /**
     * The date format(s) accepted by a date field.
     */
    public function format(string $format): self
    {
        $this->options['format'] = $format;

        return $this;
    }

    /**
     * Whether the field is indexed (searchable).
     */
    public function index(bool $index = true): self
    {
        $this->options['index'] = $index;

        return $this;
    }

    /**
     * Whether doc values (sorting/aggregations) are enabled.
     */
    public function docValues(bool $enabled = true): self
    {
        $this->options['doc_values'] = $enabled;

        return $this;
    }

    /**
     * The boost applied at query time.
     */
    public function boost(float $boost): self
    {
        $this->options['boost'] = $boost;

        return $this;
    }

    /**
     * Skip indexing terms longer than the given length.
     */
    public function ignoreAbove(int $length): self
    {
        $this->options['ignore_above'] = $length;

        return $this;
    }

    /**
     * The value substituted for `null` at index time.
     */
    public function nullValue(mixed $value): self
    {
        $this->options['null_value'] = $value;

        return $this;
    }

    /**
     * Arbitrary field metadata.
     *
     * @param array<string, mixed> $meta
     */
    public function meta(array $meta): self
    {
        $this->options['meta'] = $meta;

        return $this;
    }

    /**
     * The dynamic mapping strategy for an object/nested field.
     */
    public function dynamic(string $dynamic): self
    {
        $this->options['dynamic'] = $dynamic;

        return $this;
    }

    /**
     * Whether an object field should be parsed.
     */
    public function enabled(bool $enabled = true): self
    {
        $this->options['enabled'] = $enabled;

        return $this;
    }

    /**
     * Add a sub-field (multi-field): `text` with an extra `keyword` sub-field.
     */
    public function field(string $name, Field $field): self
    {
        /** @var array<string, Field|array<string, mixed>> $fields */
        $fields = $this->options['fields'] ?? [];
        $fields[$name] = $field;

        $this->options['fields'] = $fields;

        return $this;
    }

    /**
     * Add a property to an object/nested field.
     */
    public function property(string $name, Field $field): self
    {
        /** @var array<string, Field|array<string, mixed>> $properties */
        $properties = $this->options['properties'] ?? [];
        $properties[$name] = $field;

        $this->options['properties'] = $properties;

        return $this;
    }

    /**
     * Set an arbitrary Elasticsearch field option.
     */
    public function option(string $name, mixed $value): self
    {
        $this->options[$name] = $value;

        return $this;
    }

    /**
     * The field type, e.g. `text`, `keyword`, `date`.
     */
    public function getType(): string
    {
        return $this->type;
    }

    /**
     * @return array<string, mixed>
     */
    public function getOptions(): array
    {
        return $this->options;
    }

    /**
     * The raw Elasticsearch property definition.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $result = ['type' => $this->type];

        foreach ($this->options as $key => $value) {
            $result[$key] = self::normalize($value);
        }

        return $result;
    }

    private static function normalize(mixed $value): mixed
    {
        if ($value instanceof self) {
            return $value->toArray();
        }

        if (is_array($value)) {
            $normalized = [];

            foreach ($value as $key => $item) {
                $normalized[$key] = self::normalize($item);
            }

            return $normalized;
        }

        return $value;
    }
}
