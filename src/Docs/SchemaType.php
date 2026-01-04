<?php

namespace EasyDoc\Docs;

/**
 * SchemaType - Fluent API for defining extra API columns.
 *
 * Usage in models:
 * ```php
 * public function addExtraAPIColumns(): array
 * {
 *     return [
 *         'access_token' => type('string')->example('eyJ0eXAi...')->nullable(),
 *         'email' => type('email')->required(),
 *         'status' => type('string')->enum(['active', 'inactive'])->default('active'),
 *         'places' => type('array')->of(Place::class),
 *         'profile' => type('object')->model(Profile::class),
 *     ];
 * }
 * ```
 */
class SchemaType
{
    protected string $type = 'string';
    protected bool $deprecated = false;
    protected ?string $description = null;
    protected mixed $example = null;
    protected ?string $modelClass = null;
    protected bool $isArray = false;
    protected bool $nullable = false;
    protected bool $required = false;
    protected ?string $format = null;
    protected ?array $enum = null;
    protected mixed $default = null;
    protected bool $hasDefault = false;
    protected ?int $minLength = null;
    protected ?int $maxLength = null;
    protected ?float $minimum = null;
    protected ?float $maximum = null;
    protected ?string $pattern = null;

    /**
     * Type format shortcuts mapping.
     * These types automatically set the format property.
     */
    protected static array $formatTypes = [
        'email' => ['type' => 'string', 'format' => 'email'],
        'url' => ['type' => 'string', 'format' => 'uri'],
        'uri' => ['type' => 'string', 'format' => 'uri'],
        'uuid' => ['type' => 'string', 'format' => 'uuid'],
        'date' => ['type' => 'string', 'format' => 'date'],
        'datetime' => ['type' => 'string', 'format' => 'date-time'],
        'date-time' => ['type' => 'string', 'format' => 'date-time'],
        'time' => ['type' => 'string', 'format' => 'time'],
        'password' => ['type' => 'string', 'format' => 'password'],
        'byte' => ['type' => 'string', 'format' => 'byte'],
        'binary' => ['type' => 'string', 'format' => 'binary'],
        'ipv4' => ['type' => 'string', 'format' => 'ipv4'],
        'ipv6' => ['type' => 'string', 'format' => 'ipv6'],
        'phone' => ['type' => 'string', 'format' => 'phone'],
    ];

    public function __construct(string $type = 'string')
    {
        // Check for format shortcuts
        if (isset(self::$formatTypes[strtolower($type)])) {
            $config = self::$formatTypes[strtolower($type)];
            $this->type = $config['type'];
            $this->format = $config['format'];
        } else {
            $this->type = $type;
        }
    }

    /**
     * Create a new SchemaType instance.
     */
    public static function make(string $type = 'string'): static
    {
        return new static($type);
    }

    /**
     * Set description for this column.
     */
    public function description(string $description): static
    {
        $this->description = $description;
        return $this;
    }

    /**
     * Set an example value.
     */
    public function example(mixed $example): static
    {
        $this->example = $example;
        return $this;
    }

    /**
     * Reference a model class (for object types).
     */
    public function model(string $modelClass): static
    {
        $this->type = 'object';
        $this->modelClass = $modelClass;
        return $this;
    }

    /**
     * Define as an array of a model class.
     */
    public function of(string $modelClass): static
    {
        $this->type = 'array';
        $this->isArray = true;
        $this->modelClass = $modelClass;
        return $this;
    }

    /**
     * Make this column nullable.
     */
    public function nullable(): static
    {
        $this->nullable = true;
        return $this;
    }

    /**
     * Mark this column as required.
     */
    public function required(): static
    {
        $this->required = true;
        return $this;
    }

    /**
     * Set format (e.g., 'email', 'date-time', 'uri').
     */
    public function format(string $format): static
    {
        $this->format = $format;
        return $this;
    }

    /**
     * Mark this column as deprecated.
     */
    public function deprecated(): static
    {
        $this->deprecated = true;
        return $this;
    }

    /**
     * Set allowed enum values.
     *
     * @param array $values List of allowed values
     */
    public function enum(array $values): static
    {
        $this->enum = $values;
        return $this;
    }

    /**
     * Set default value.
     */
    public function default(mixed $value): static
    {
        $this->default = $value;
        $this->hasDefault = true;
        return $this;
    }

    /**
     * Set minimum length for strings.
     */
    public function minLength(int $length): static
    {
        $this->minLength = $length;
        return $this;
    }

    /**
     * Set maximum length for strings.
     */
    public function maxLength(int $length): static
    {
        $this->maxLength = $length;
        return $this;
    }

    /**
     * Set minimum value for numbers.
     */
    public function min(float $value): static
    {
        $this->minimum = $value;
        return $this;
    }

    /**
     * Set maximum value for numbers.
     */
    public function max(float $value): static
    {
        $this->maximum = $value;
        return $this;
    }

    /**
     * Set regex pattern for validation.
     */
    public function pattern(string $pattern): static
    {
        $this->pattern = $pattern;
        return $this;
    }

    // ========== Getters ==========

    /**
     * Get the type.
     */
    public function getType(): string
    {
        return $this->type;
    }

    /**
     * Get the description.
     */
    public function getDescription(): ?string
    {
        return $this->description;
    }

    /**
     * Get the example.
     */
    public function getExample(): mixed
    {
        return $this->example;
    }

    /**
     * Get the model class.
     */
    public function getModelClass(): ?string
    {
        return $this->modelClass;
    }

    /**
     * Check if this is an array type.
     */
    public function isArray(): bool
    {
        return $this->isArray;
    }

    /**
     * Check if nullable.
     */
    public function isNullable(): bool
    {
        return $this->nullable;
    }

    /**
     * Check if required.
     */
    public function isRequired(): bool
    {
        return $this->required;
    }

    /**
     * Get format.
     */
    public function getFormat(): ?string
    {
        return $this->format;
    }

    /**
     * Get enum values.
     */
    public function getEnum(): ?array
    {
        return $this->enum;
    }

    /**
     * Get default value.
     */
    public function getDefault(): mixed
    {
        return $this->default;
    }

    /**
     * Check if has default.
     */
    public function hasDefault(): bool
    {
        return $this->hasDefault;
    }

    /**
     * Convert to Swagger schema array.
     */
    public function toSchema(): array
    {
        $schema = [];

        // Handle model references
        if ($this->modelClass) {
            $reflection = new \ReflectionClass($this->modelClass);
            $schemaName = $reflection->getShortName();

            if ($this->isArray) {
                $schema['type'] = 'array';
                $schema['items'] = ['$ref' => '#/definitions/' . $schemaName];
            } else {
                $schema['$ref'] = '#/definitions/' . $schemaName;
            }
        } else {
            $schema['type'] = $this->normalizeType($this->type);
        }

        if ($this->description) {
            $schema['description'] = $this->description;
        }

        if ($this->example !== null) {
            $schema['example'] = $this->example;
        }

        if ($this->nullable) {
            $schema['nullable'] = true;
        }

        if ($this->format) {
            $schema['format'] = $this->format;
        }

        if ($this->enum !== null) {
            $schema['enum'] = $this->enum;
        }

        if ($this->hasDefault) {
            $schema['default'] = $this->default;
        }

        if ($this->minLength !== null) {
            $schema['minLength'] = $this->minLength;
        }

        if ($this->maxLength !== null) {
            $schema['maxLength'] = $this->maxLength;
        }

        if ($this->minimum !== null) {
            $schema['minimum'] = $this->minimum;
        }

        if ($this->maximum !== null) {
            $schema['maximum'] = $this->maximum;
        }

        if ($this->pattern !== null) {
            $schema['pattern'] = $this->pattern;
        }

        if ($this->deprecated) {
            $schema['deprecated'] = true;
        }

        return $schema;
    }

    /**
     * Normalize type to Swagger type.
     */
    protected function normalizeType(string $type): string
    {
        return match (strtolower($type)) {
            'int', 'integer' => 'integer',
            'float', 'double', 'decimal', 'number' => 'number',
            'bool', 'boolean' => 'boolean',
            'array' => 'array',
            'object' => 'object',
            default => 'string',
        };
    }
}
