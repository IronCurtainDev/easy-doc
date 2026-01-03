<?php

namespace EasyDoc\Docs;

use Illuminate\Contracts\Support\Arrayable;

/**
 * Param class for defining API parameters and headers.
 */
class Param implements Arrayable, \JsonSerializable
{
    // Parameter Locations
    public const LOCATION_PATH = 'path';
    public const LOCATION_QUERY = 'query';
    public const LOCATION_HEADER = 'header';
    public const LOCATION_COOKIE = 'cookie';
    public const LOCATION_BODY = 'body';
    public const LOCATION_FORM = 'formData';

    // Data Types
    public const TYPE_STRING = 'string';
    public const TYPE_INT = 'integer';
    public const TYPE_NUMBER = 'number';
    public const TYPE_FLOAT = 'number';
    public const TYPE_DOUBLE = 'number';
    public const TYPE_BOOLEAN = 'boolean';
    public const TYPE_ARRAY = 'array';
    public const TYPE_FILE = 'file';

    protected ?string $fieldName = null;
    protected bool $required = true;
    protected string $dataType = self::TYPE_STRING;
    protected mixed $defaultValue = null;
    protected string $description = '';
    protected ?string $location = null;
    protected mixed $model = null;
    protected ?string $collectionFormat = null;
    protected mixed $items = null;
    protected ?string $variable = null;
    protected mixed $example = null;
    protected ?array $enum = null;
    protected mixed $min = null;
    protected mixed $max = null;
    protected ?string $pattern = null;

    public function __construct(
        ?string $fieldName = null,
        string $dataType = self::TYPE_STRING,
        ?string $description = null,
        ?string $location = null
    ) {
        $this->fieldName = $fieldName;
        $this->setDataType($dataType);
        $this->location = $location;

        if (!$description && $fieldName) {
            $this->description = ucfirst(str_replace('_', ' ', $fieldName));
        } else {
            $this->description = $description ?? '';
        }
    }

    /**
     * Create a new Param instance.
     */
    public static function make(
        ?string $fieldName = null,
        string $dataType = self::TYPE_STRING,
        ?string $description = null
    ): static {
        return new static($fieldName, $dataType, $description);
    }

    /**
     * Create a header param.
     */
    public static function header(string $name, ?string $description = null): static
    {
        $param = new static($name, self::TYPE_STRING, $description, self::LOCATION_HEADER);
        return $param;
    }

    public static function getParamLocations(): array
    {
        return [
            self::LOCATION_HEADER,
            self::LOCATION_PATH,
            self::LOCATION_QUERY,
            self::LOCATION_FORM,
        ];
    }

    public static function getDataTypes(): array
    {
        return [
            self::TYPE_STRING,
            self::TYPE_INT,
            self::TYPE_NUMBER,
            self::TYPE_FLOAT,
            self::TYPE_DOUBLE,
            self::TYPE_BOOLEAN,
            self::TYPE_ARRAY,
        ];
    }

    public function getRequired(): bool
    {
        return $this->required;
    }

    public function required(): static
    {
        $this->required = true;
        return $this;
    }

    public function optional(): static
    {
        $this->required = false;
        return $this;
    }

    public function getDataType(): string
    {
        return ucfirst($this->dataType);
    }

    public function dataType(string $dataType): static
    {
        $this->dataType = $dataType;
        return $this;
    }

    public function getDefaultValue(): mixed
    {
        return $this->defaultValue;
    }

    public function defaultValue(mixed $defaultValue): static
    {
        $this->defaultValue = $defaultValue;
        return $this;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function description(string $description): static
    {
        $this->description = $description;
        return $this;
    }

    public function getName(): ?string
    {
        return $this->fieldName;
    }

    public function field(string $fieldName): static
    {
        $this->fieldName = $fieldName;
        return $this;
    }

    public function getLocation(): ?string
    {
        return $this->location;
    }

    public function setLocation(string $location): static
    {
        $this->location = $location;
        return $this;
    }

    public function setModel(mixed $model): static
    {
        $this->model = $model;
        return $this;
    }

    public function getModel(): mixed
    {
        return $this->model;
    }

    public function setDefaultValue(mixed $defaultValue): static
    {
        $this->defaultValue = $defaultValue;
        return $this;
    }

    public static function getSwaggerDataType(string $dataType): string
    {
        $dataType = strtolower($dataType);

        return match ($dataType) {
            'integer', 'int' => 'integer',
            'float', 'double' => 'number',
            'boolean', 'bool' => 'boolean',
            'array' => 'array',
            'object', 'model' => 'object',
            'file' => 'file',
            default => 'string',
        };
    }

    public function toArray(): array
    {
        return [
            'fieldName' => $this->fieldName,
            'required' => $this->required,
            'dataType' => $this->dataType,
            'defaultValue' => $this->defaultValue,
            'location' => $this->location,
            'model' => $this->model,
            'variable' => $this->variable,
            'example' => $this->example,
            'collectionFormat' => $this->collectionFormat,
            'items' => $this->items,
            'enum' => $this->enum,
            'min' => $this->min,
            'max' => $this->max,
            'pattern' => $this->pattern,
        ];
    }

    public function jsonSerialize(): mixed
    {
        return $this->toArray();
    }

    public function getExample(): mixed
    {
        return $this->example;
    }

    public function setExample(mixed $example): static
    {
        $this->example = $example;
        return $this;
    }

    public function example(mixed $example): static
    {
        return $this->setExample($example);
    }

    public function getVariable(): ?string
    {
        return $this->variable;
    }

    public function setVariable(string $variable): static
    {
        // Clean up and add the braces if they're not there
        $variable = '{{' . trim($variable, " \t\n\r\0\x0B{}") . '}}';
        $this->variable = trim($variable);
        return $this;
    }

    public function setDataType(string $dataType): static
    {
        $this->dataType = $dataType;

        // Set the default array type
        if ($dataType === self::TYPE_ARRAY) {
            $this->setCollectionFormat('multi');
            $this->setArrayType(self::TYPE_STRING);
        }

        return $this;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description ?? '';
        return $this;
    }

    public function getCollectionFormat(): string
    {
        return $this->collectionFormat ?? '';
    }

    public function getItems(): mixed
    {
        return $this->items;
    }

    public function setCollectionFormat(string $collectionFormat): static
    {
        $this->collectionFormat = $collectionFormat;
        return $this;
    }

    public function setItems(mixed $items): static
    {
        $this->items = $items;
        return $this;
    }

    public function setArrayType(string $dataType): static
    {
        $this->items = [
            'type' => $dataType,
        ];

        return $this;
    }

    /**
     * Set allowed enum values for this parameter.
     */
    public function enum(array $values): static
    {
        $this->enum = $values;
        return $this;
    }

    /**
     * Get enum values.
     */
    public function getEnum(): ?array
    {
        return $this->enum;
    }

    /**
     * Set minimum value constraint.
     */
    public function min(int|float $value): static
    {
        $this->min = $value;
        return $this;
    }

    /**
     * Get minimum value.
     */
    public function getMin(): int|float|null
    {
        return $this->min;
    }

    /**
     * Set maximum value constraint.
     */
    public function max(int|float $value): static
    {
        $this->max = $value;
        return $this;
    }

    /**
     * Get maximum value.
     */
    public function getMax(): int|float|null
    {
        return $this->max;
    }

    /**
     * Set regex pattern for validation.
     */
    public function pattern(string $regex): static
    {
        $this->pattern = $regex;
        return $this;
    }

    /**
     * Get regex pattern.
     */
    public function getPattern(): ?string
    {
        return $this->pattern;
    }
}
