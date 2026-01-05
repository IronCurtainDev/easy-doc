<?php

declare(strict_types=1);

namespace EasyDoc\Docs;

/**
 * SchemaBuilder for defining reusable API schemas/models.
 *
 * Use this to define common response structures that can be referenced
 * across multiple endpoints.
 */
class SchemaBuilder
{
    /**
     * Registered schemas.
     */
    protected static array $schemas = [];

    /**
     * Define a new schema.
     *
     * @param string $name Schema name (e.g., 'User', 'ErrorResponse')
     * @param array $properties Schema properties as ['field' => 'type'] or Param objects
     */
    public static function define(string $name, array $properties): void
    {
        $schema = [
            'type' => 'object',
            'properties' => [],
            'required' => [],
        ];

        foreach ($properties as $key => $value) {
            if ($value instanceof Param) {
                $schema['properties'][$value->getName()] = self::paramToSchema($value);
                if ($value->getRequired()) {
                    $schema['required'][] = $value->getName();
                }
            } elseif (is_string($value)) {
                // Simple type definition: ['name' => 'string']
                $schema['properties'][$key] = [
                    'type' => Param::getSwaggerDataType($value),
                ];
            } elseif (is_array($value)) {
                // Detailed definition: ['name' => ['type' => 'string', 'description' => '...']]
                $schema['properties'][$key] = $value;
            }
        }

        self::$schemas[$name] = $schema;
    }

    /**
     * Convert a Param to schema definition.
     */
    protected static function paramToSchema(Param $param): array
    {
        $schema = [
            'type' => Param::getSwaggerDataType($param->getDataType()),
            'description' => $param->getDescription(),
        ];

        if ($param->getExample() !== null) {
            $schema['example'] = $param->getExample();
        }

        if ($param->getEnum() !== null) {
            $schema['enum'] = $param->getEnum();
        }

        if ($param->getMin() !== null) {
            $schema['minimum'] = $param->getMin();
        }

        if ($param->getMax() !== null) {
            $schema['maximum'] = $param->getMax();
        }

        if ($param->getPattern() !== null) {
            $schema['pattern'] = $param->getPattern();
        }

        if ($param->getDefaultValue() !== null) {
            $schema['default'] = $param->getDefaultValue();
        }

        return $schema;
    }

    /**
     * Define a schema with an example.
     */
    public static function defineWithExample(string $name, array $properties, array $example): void
    {
        self::define($name, $properties);
        self::$schemas[$name]['example'] = $example;
    }

    /**
     * Get a defined schema by name.
     */
    public static function get(string $name): ?array
    {
        return self::$schemas[$name] ?? null;
    }

    /**
     * Get all defined schemas.
     */
    public static function all(): array
    {
        return self::$schemas;
    }

    /**
     * Check if a schema exists.
     */
    public static function has(string $name): bool
    {
        return isset(self::$schemas[$name]);
    }

    /**
     * Clear all schemas (useful for testing).
     */
    public static function clear(): void
    {
        self::$schemas = [];
    }

    /**
     * Define a common User schema.
     */
    public static function defineUser(array $additional = []): void
    {
        $properties = array_merge([
            'id' => 'integer',
            'name' => 'string',
            'email' => 'string',
            'created_at' => 'string',
            'updated_at' => 'string',
        ], $additional);

        self::define('User', $properties);
    }

    /**
     * Define a common paginated response schema.
     */
    public static function definePaginated(string $itemsSchemaName): void
    {
        self::define('Paginated' . $itemsSchemaName, [
            'data' => [
                'type' => 'array',
                'items' => ['$ref' => '#/definitions/' . $itemsSchemaName],
            ],
            'meta' => [
                'type' => 'object',
                'properties' => [
                    'current_page' => ['type' => 'integer'],
                    'from' => ['type' => 'integer'],
                    'last_page' => ['type' => 'integer'],
                    'per_page' => ['type' => 'integer'],
                    'to' => ['type' => 'integer'],
                    'total' => ['type' => 'integer'],
                ],
            ],
            'links' => [
                'type' => 'object',
                'properties' => [
                    'first' => ['type' => 'string'],
                    'last' => ['type' => 'string'],
                    'prev' => ['type' => 'string', 'nullable' => true],
                    'next' => ['type' => 'string', 'nullable' => true],
                ],
            ],
        ]);
    }

    /**
     * Define a standard error response schema.
     */
    public static function defineErrorResponse(): void
    {
        self::define('ErrorResponse', [
            'success' => [
                'type' => 'boolean',
                'example' => false,
            ],
            'message' => [
                'type' => 'string',
                'example' => 'The given data was invalid.',
            ],
            'errors' => [
                'type' => 'object',
                'additionalProperties' => [
                    'type' => 'array',
                    'items' => ['type' => 'string'],
                ],
            ],
        ]);
    }

    /**
     * Define a standard success response schema.
     */
    public static function defineSuccessResponse(): void
    {
        self::define('SuccessResponse', [
            'success' => [
                'type' => 'boolean',
                'example' => true,
            ],
            'message' => [
                'type' => 'string',
                'example' => 'Operation completed successfully.',
            ],
            'data' => [
                'type' => 'object',
            ],
        ]);
    }

    /**
     * Define a schema from an Eloquent model.
     * Automatically reads the database columns and their types.
     * With "Smart Examples" using Faker if available.
     *
     * If the model has an `addExtraAPIColumns()` method, those columns
     * will be merged into the schema.
     *
     * @param string $modelClass Fully qualified model class name (e.g., App\Models\User::class)
     * @param string|null $schemaName Optional custom schema name (defaults to model basename)
     * @param array $exclude Columns to exclude from schema
     * @param array $include Additional properties to include
     */
    public static function fromModel(
        string $modelClass,
        ?string $schemaName = null,
        array $exclude = ['password', 'remember_token'],
        array $include = []
    ): void {
        if (!class_exists($modelClass)) {
            throw new \InvalidArgumentException("Model class {$modelClass} does not exist.");
        }

        // Get model instance
        $model = new $modelClass;

        // Use reflection to get the short class name
        $reflection = new \ReflectionClass($modelClass);
        $name = $schemaName ?? $reflection->getShortName();

        // Get the table name
        $table = $model->getTable();

        // Get database connection
        $connection = $model->getConnection();

        // Get columns from database
        $columns = $connection->getSchemaBuilder()->getColumnListing($table);

        $properties = [];
        $required = [];

        foreach ($columns as $column) {
            // Skip excluded columns
            if (in_array($column, $exclude)) {
                continue;
            }

            // Get column type
            $type = $connection->getSchemaBuilder()->getColumnType($table, $column);
            $swaggerType = self::databaseTypeToSwagger($type);

            $properties[$column] = [
                'type' => $swaggerType,
                'description' => ucfirst(str_replace('_', ' ', $column)),
                'example' => self::generateFakerExample($column, $swaggerType),
            ];

            // Add nullable info
            $isTimestamp = in_array($column, ['created_at', 'updated_at', 'deleted_at', 'email_verified_at']);
            $isId = $column === 'id' || $column === 'uuid';

            // Heuristic: If we can't determine nullability from DB without Doctrine,
            // we assume ID and timestamps (except deleted_at) are required.
            // Everything else is optional by default to be safe.
            if ($isId || ($isTimestamp && $column !== 'deleted_at' && $column !== 'email_verified_at')) {
                $required[] = $column;
            } else {
                $properties[$column]['nullable'] = true;
            }
        }

        // Merge with additional properties from $include parameter
        $properties = array_merge($properties, $include);

        // Check for addExtraAPIColumns method on the model
        if (method_exists($model, 'addExtraAPIColumns')) {
            $extraColumns = $model->addExtraAPIColumns();
            $merged = self::mergeExtraColumns($properties, $extraColumns, $modelClass, $required);
            $properties = $merged['properties'];
            $required = $merged['required'];
        }

        // Directly set the schema (don't use define() which would double-wrap)
        self::$schemas[$name] = [
            'type' => 'object',
            'properties' => $properties,
            'required' => $required ?? [],
        ];
    }

    /**
     * Merge extra API columns defined in model into properties.
     * Supports SchemaType instances for fluent API.
     *
     * @param array $properties Existing properties
     * @param array $extraColumns Extra columns from addExtraAPIColumns()
     * @param string $parentModelClass The parent model class (for auto-registering related models)
     * @param array $existingRequired Existing required fields
     * @return array{properties: array, required: array} Merged properties and required fields
     */
    protected static function mergeExtraColumns(array $properties, array $extraColumns, string $parentModelClass, array $existingRequired = []): array
    {
        $additionalRequired = [];

        foreach ($extraColumns as $columnName => $columnDef) {
            if ($columnDef instanceof SchemaType) {
                // Register related model schemas if needed
                $modelClass = $columnDef->getModelClass();
                if ($modelClass && class_exists($modelClass) && !self::has((new \ReflectionClass($modelClass))->getShortName())) {
                    // Auto-register the related model schema
                    self::fromModel($modelClass);
                }

                $properties[$columnName] = $columnDef->toSchema();

                // Check if this column is marked as required
                if ($columnDef->isRequired()) {
                    $additionalRequired[] = $columnName;
                }
            } elseif (is_array($columnDef)) {
                // Already a schema array
                $properties[$columnName] = $columnDef;
            } elseif (is_string($columnDef)) {
                // Simple type string
                $properties[$columnName] = [
                    'type' => self::databaseTypeToSwagger($columnDef),
                    'description' => ucfirst(str_replace('_', ' ', $columnName)),
                ];
            }
        }

        return [
            'properties' => $properties,
            'required' => array_merge($existingRequired, $additionalRequired),
        ];
    }

    /**
     * Generate a realistic example value using Faker based on column name and type.
     */
    protected static function generateFakerExample(string $column, string $type): mixed
    {
        // Use the global fake() helper if available, otherwise return null
        if (!function_exists('fake')) {
            return null;
        }

        $faker = fake();

        // 1. Column Name Matches
        if (str_contains($column, 'email')) return $faker->safeEmail();
        if ($column === 'name' || str_contains($column, 'full_name')) return $faker->name();
        if (str_contains($column, 'first_name')) return $faker->firstName();
        if (str_contains($column, 'last_name')) return $faker->lastName();
        if (str_contains($column, 'phone')) return $faker->phoneNumber();
        if (str_contains($column, 'address')) return $faker->address();
        if (str_contains($column, 'city')) return $faker->city();
        if (str_contains($column, 'country')) return $faker->country();
        if (str_contains($column, 'zip') || str_contains($column, 'postal')) return $faker->postcode();
        if ($column === 'title') return $faker->jobTitle();
        if (str_contains($column, 'url') || str_contains($column, 'link')) return $faker->url();
        if (str_contains($column, 'uuid')) return $faker->uuid();
        if (str_contains($column, 'ip_address')) return $faker->ipv4();
        if (str_contains($column, 'image') || str_contains($column, 'avatar')) return $faker->imageUrl();
        if (str_contains($column, 'password')) return 'secret';

        // 2. Type/Suffix Matches
        if ($column === 'id') return $faker->numberBetween(1, 100);
        if (str_ends_with($column, '_id')) return $faker->numberBetween(1, 50);
        if (str_ends_with($column, '_at') || str_contains($column, 'date')) return $faker->dateTime()->format('Y-m-d H:i:s');

        // 3. Fallback by Type
        return match ($type) {
            'integer' => $faker->numberBetween(1, 1000),
            'boolean' => $faker->boolean(),
            'number' => $faker->randomFloat(2, 10, 1000),
            'string' => str_contains($column, 'description') || str_contains($column, 'content')
                ? $faker->sentence()
                : $faker->word(),
            default => null,
        };
    }

    /**
     * Convert database column types to Swagger/OpenAPI types.
     */
    protected static function databaseTypeToSwagger(string $dbType): string
    {
        return match (strtolower($dbType)) {
            'int', 'integer', 'bigint', 'smallint', 'tinyint', 'mediumint' => 'integer',
            'float', 'double', 'decimal', 'real', 'numeric' => 'number',
            'bool', 'boolean' => 'boolean',
            'date', 'datetime', 'timestamp', 'time', 'year' => 'string',
            'json', 'jsonb', 'array' => 'object',
            'binary', 'blob', 'varbinary' => 'string',
            default => 'string',
        };
    }

    /**
     * Define schemas for multiple models at once.
     *
     * @param array $models Array of model class names or [class => schemaName] pairs
     * @param array $globalExclude Columns to exclude from all schemas
     */
    public static function fromModels(array $models, array $globalExclude = ['password', 'remember_token']): void
    {
        foreach ($models as $key => $value) {
            if (is_string($key)) {
                // ['App\Models\User' => 'UserSchema']
                self::fromModel($key, $value, $globalExclude);
            } else {
                // ['App\Models\User']
                self::fromModel($value, null, $globalExclude);
            }
        }
    }

    /**
     * Define schema from model's fillable attributes only.
     *
     * @param string $modelClass Fully qualified model class name
     * @param string|null $schemaName Optional custom schema name
     */
    public static function fromModelFillable(string $modelClass, ?string $schemaName = null): void
    {
        if (!class_exists($modelClass)) {
            throw new \InvalidArgumentException("Model class {$modelClass} does not exist.");
        }

        $model = new $modelClass;
        $fillable = $model->getFillable();

        $reflection = new \ReflectionClass($modelClass);
        $name = $schemaName ?? $reflection->getShortName() . 'Input';

        $table = $model->getTable();
        $connection = $model->getConnection();

        $properties = [];

        foreach ($fillable as $column) {
            $type = $connection->getSchemaBuilder()->getColumnType($table, $column);

            $properties[$column] = [
                'type' => self::databaseTypeToSwagger($type),
                'description' => ucfirst(str_replace('_', ' ', $column)),
            ];
        }

        self::define($name, $properties);
    }

    /**
     * Define a schema from a model with its relationships.
     *
     * @param string $modelClass Fully qualified model class name
     * @param array $relations Array of relation names and their config
     *                         e.g., ['places' => Place::class] or
     *                         ['places' => ['model' => Place::class, 'type' => 'hasMany']]
     * @param string|null $schemaName Optional custom schema name
     * @param array $exclude Columns to exclude
     */
    public static function fromModelWithRelations(
        string $modelClass,
        array $relations,
        ?string $schemaName = null,
        array $exclude = ['password', 'remember_token']
    ): void {
        // First, define the main model schema
        self::fromModel($modelClass, $schemaName, $exclude);

        $reflection = new \ReflectionClass($modelClass);
        $mainSchemaName = $schemaName ?? $reflection->getShortName();

        // Define related model schemas
        foreach ($relations as $relationName => $relationConfig) {
            $relatedClass = is_array($relationConfig) ? $relationConfig['model'] : $relationConfig;
            $relationType = is_array($relationConfig) ? ($relationConfig['type'] ?? 'hasMany') : 'hasMany';

            // Create schema for related model if not exists
            $relatedReflection = new \ReflectionClass($relatedClass);
            $relatedSchemaName = $relatedReflection->getShortName();

            if (!self::has($relatedSchemaName)) {
                self::fromModel($relatedClass, $relatedSchemaName, $exclude);
            }

            // Add the relation to the main schema
            if ($relationType === 'hasMany') {
                // hasMany relationship - array of objects
                self::$schemas[$mainSchemaName]['properties'][$relationName] = [
                    'type' => 'array',
                    'items' => ['$ref' => '#/definitions/' . $relatedSchemaName],
                    'description' => ucfirst(str_replace('_', ' ', $relationName)),
                ];
            } else {
                // hasOne/belongsTo relationship - single object
                self::$schemas[$mainSchemaName]['properties'][$relationName] = [
                    '$ref' => '#/definitions/' . $relatedSchemaName,
                    'description' => ucfirst(str_replace('_', ' ', $relationName)),
                ];
            }
        }
    }

    /**
     * Define schema with response wrapper (success, message, data).
     *
     * @param string $name Schema name
     * @param string $dataSchemaName Referenced schema for data field
     * @param bool $isArray Whether data contains array of items
     */
    public static function defineResponseWrapper(
        string $name,
        string $dataSchemaName,
        bool $isArray = false
    ): void {
        $dataProperty = $isArray
            ? ['type' => 'array', 'items' => ['$ref' => '#/definitions/' . $dataSchemaName]]
            : ['$ref' => '#/definitions/' . $dataSchemaName];

        self::define($name, [
            'success' => ['type' => 'boolean', 'example' => true],
            'message' => ['type' => 'string', 'example' => 'Success'],
            'data' => $dataProperty,
        ]);
    }

    /**
     * Define a Laravel API Resource style response (industry standard).
     *
     * @param string $name Schema name (e.g., 'UserResource')
     * @param string $modelClass The model class
     * @param array $relations Relations: ['places' => Place::class] or ['profile' => ['model' => Profile::class, 'type' => 'hasOne']]
     * @param array $additionalFields Extra computed fields: ['full_name' => 'string', 'avatar_url' => 'string']
     */
    public static function defineResource(
        string $name,
        string $modelClass,
        array $relations = [],
        array $additionalFields = []
    ): void {
        $reflection = new \ReflectionClass($modelClass);
        $baseSchemaName = $reflection->getShortName();

        if (!self::has($baseSchemaName)) {
            self::fromModel($modelClass);
        }

        $properties = self::$schemas[$baseSchemaName]['properties'] ?? [];

        // Add relations
        foreach ($relations as $relName => $relConfig) {
            $relatedClass = is_array($relConfig) ? $relConfig['model'] : $relConfig;
            $relType = is_array($relConfig) ? ($relConfig['type'] ?? 'hasMany') : 'hasMany';

            $relatedReflection = new \ReflectionClass($relatedClass);
            $relatedSchemaName = $relatedReflection->getShortName();

            if (!self::has($relatedSchemaName)) {
                self::fromModel($relatedClass);
            }

            $properties[$relName] = $relType === 'hasMany'
                ? ['type' => 'array', 'items' => ['$ref' => '#/definitions/' . $relatedSchemaName]]
                : ['$ref' => '#/definitions/' . $relatedSchemaName];
        }

        // Add additional computed fields
        foreach ($additionalFields as $fieldName => $fieldConfig) {
            $properties[$fieldName] = is_string($fieldConfig)
                ? ['type' => self::databaseTypeToSwagger($fieldConfig)]
                : $fieldConfig;
        }

        self::$schemas[$name] = ['type' => 'object', 'properties' => $properties, 'required' => []];
    }

    /**
     * Define a success response wrapping a resource.
     * Pattern: { success: true, message: "...", data: {...} }
     */
    public static function defineSuccessResponseFor(string $resourceName, bool $isCollection = false): void
    {
        $name = $isCollection ? $resourceName . 'CollectionResponse' : $resourceName . 'Response';
        $dataSchema = $isCollection
            ? ['type' => 'array', 'items' => ['$ref' => '#/definitions/' . $resourceName]]
            : ['$ref' => '#/definitions/' . $resourceName];

        $configWrapper = config('easy-doc.response_wrapper');

        if ($configWrapper) {
            $properties = [];
            foreach ($configWrapper as $key => $example) {
                if ($example === '__DATA__') {
                    $properties[$key] = $dataSchema;
                } else {
                    $type = gettype($example);
                    $swaggerType = match ($type) {
                        'boolean' => 'boolean',
                        'integer' => 'integer',
                        'double' => 'number',
                        default => 'string',
                    };
                    $properties[$key] = [
                        'type' => $swaggerType,
                        'example' => $example,
                    ];
                }
            }
            self::$schemas[$name] = [
                'type' => 'object',
                'properties' => $properties,
            ];
        } else {
            // Default Standard Wrapper
            self::$schemas[$name] = [
                'type' => 'object',
                'properties' => [
                    'success' => ['type' => 'boolean', 'example' => true],
                    'message' => ['type' => 'string', 'example' => 'Operation successful'],
                    'data' => $dataSchema,
                ],
                'required' => ['success', 'data'],
            ];
        }
    }

    /**
     * Define a paginated collection response (Laravel standard).
     * Pattern: { success: true, data: [...], meta: { pagination... }, links: {...} }
     */
    public static function definePaginatedResponseFor(string $resourceName): void
    {
        $metaSchema = [
            'type' => 'object',
            'properties' => [
                'current_page' => ['type' => 'integer', 'example' => 1],
                'from' => ['type' => 'integer', 'example' => 1],
                'last_page' => ['type' => 'integer', 'example' => 10],
                'per_page' => ['type' => 'integer', 'example' => 15],
                'to' => ['type' => 'integer', 'example' => 15],
                'total' => ['type' => 'integer', 'example' => 150],
            ],
        ];

        $linksSchema = [
            'type' => 'object',
            'properties' => [
                'first' => ['type' => 'string'],
                'last' => ['type' => 'string'],
                'prev' => ['type' => 'string', 'nullable' => true],
                'next' => ['type' => 'string', 'nullable' => true],
            ],
        ];

        $dataSchema = ['type' => 'array', 'items' => ['$ref' => '#/definitions/' . $resourceName]];

        $configWrapper = config('easy-doc.response_wrapper');

        if ($configWrapper) {
            $properties = [];
            foreach ($configWrapper as $key => $example) {
                if ($example === '__DATA__') {
                    $properties[$key] = $dataSchema;
                } else {
                    $type = gettype($example);
                    $swaggerType = match ($type) {
                        'boolean' => 'boolean',
                        'integer' => 'integer',
                        'double' => 'number',
                        default => 'string',
                    };
                    $properties[$key] = [
                        'type' => $swaggerType,
                        'example' => $example,
                    ];
                }
            }
            // Always append meta and links for pagination
            $properties['meta'] = $metaSchema;
            $properties['links'] = $linksSchema;

            self::$schemas[$resourceName . 'PaginatedResponse'] = [
                'type' => 'object',
                'properties' => $properties,
            ];
        } else {
            self::$schemas[$resourceName . 'PaginatedResponse'] = [
                'type' => 'object',
                'properties' => [
                    'success' => ['type' => 'boolean', 'example' => true],
                    'message' => ['type' => 'string'],
                    'data' => $dataSchema,
                    'meta' => $metaSchema,
                    'links' => $linksSchema,
                ],
                'required' => ['success', 'data', 'meta'],
            ];
        }
    }

    /**
     * Define ALL response variants for a resource (One, Collection, Paginated).
     *
     * Generates:
     * - {ResourceName}Response (Single object)
     * - {ResourceName}CollectionResponse (List of objects)
     * - {ResourceName}PaginatedResponse (Paginated list)
     *
     * @param string $resourceName The resource schema name
     */
    public static function defineAllResponses(string $resourceName): void
    {
        self::defineSuccessResponseFor($resourceName, isCollection: false);
        self::defineSuccessResponseFor($resourceName, isCollection: true);
        self::definePaginatedResponseFor($resourceName);
    }
}
