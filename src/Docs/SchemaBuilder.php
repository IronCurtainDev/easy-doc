<?php

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

        foreach ($columns as $column) {
            // Skip excluded columns
            if (in_array($column, $exclude)) {
                continue;
            }

            // Get column type
            $type = $connection->getSchemaBuilder()->getColumnType($table, $column);

            $properties[$column] = [
                'type' => self::databaseTypeToSwagger($type),
                'description' => ucfirst(str_replace('_', ' ', $column)),
            ];

            // Add nullable info
            if ($column !== 'id' && !in_array($column, ['created_at', 'updated_at'])) {
                // Most fields could be nullable, but we don't make them required by default
            }
        }

        // Merge with additional properties
        $properties = array_merge($properties, $include);

        self::define($name, $properties);
    }

    /**
     * Convert database column type to Swagger type.
     */
    protected static function databaseTypeToSwagger(string $dbType): string
    {
        return match (strtolower($dbType)) {
            'integer', 'int', 'smallint', 'bigint', 'tinyint' => 'integer',
            'decimal', 'float', 'double', 'real' => 'number',
            'boolean', 'bool' => 'boolean',
            'date', 'datetime', 'timestamp', 'time' => 'string',
            'json', 'jsonb' => 'object',
            'text', 'longtext', 'mediumtext' => 'string',
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

    /**
     * Define a paginated collection response (Laravel standard).
     * Pattern: { success: true, data: [...], meta: { pagination... }, links: {...} }
     */
    public static function definePaginatedResponseFor(string $resourceName): void
    {
        self::$schemas[$resourceName . 'PaginatedResponse'] = [
            'type' => 'object',
            'properties' => [
                'success' => ['type' => 'boolean', 'example' => true],
                'message' => ['type' => 'string'],
                'data' => ['type' => 'array', 'items' => ['$ref' => '#/definitions/' . $resourceName]],
                'meta' => [
                    'type' => 'object',
                    'properties' => [
                        'current_page' => ['type' => 'integer', 'example' => 1],
                        'from' => ['type' => 'integer', 'example' => 1],
                        'last_page' => ['type' => 'integer', 'example' => 10],
                        'per_page' => ['type' => 'integer', 'example' => 15],
                        'to' => ['type' => 'integer', 'example' => 15],
                        'total' => ['type' => 'integer', 'example' => 150],
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
            ],
            'required' => ['success', 'data', 'meta'],
        ];
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
