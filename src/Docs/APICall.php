<?php

namespace EasyDoc\Docs;

/**
 * APICall class for defining API endpoint documentation.
 */
class APICall
{
    public const CONSUME_JSON = 'application/json';
    public const CONSUME_FORM_URLENCODED = 'application/x-www-form-urlencoded';
    public const CONSUME_MULTIPART = 'multipart/form-data';

    protected ?string $route = null;
    protected string $method = 'GET';
    protected ?string $group = null;
    protected ?string $name = null;
    protected ?string $description = null;
    protected string $version = '1.0.0';
    protected array $params = [];
    protected array $headers = [];
    protected array $successParams = [];
    protected array $requestExample = [];
    protected array $define = [];
    protected array $use = [];
    protected bool $addDefaultHeaders = true;
    protected array $consumes = [];
    protected mixed $successObject = null;
    protected mixed $successPaginatedObject = null;
    protected bool $successMessageOnly = false;
    protected ?string $operationId = null;
    protected array $successExamples = [];
    protected array $errorExamples = [];
    protected array $queryParams = [];
    protected array $pathParams = [];
    protected array $tags = [];
    protected ?string $deprecated = null;
    protected ?array $rateLimit = null;
    protected ?string $successSchema = null;
    protected ?string $errorSchema = null;

    public function getRoute(): ?string
    {
        return $this->route;
    }

    public function setRoute(string $route): static
    {
        $this->route = $route;
        return $this;
    }

    public function getParams(): array
    {
        return $this->params;
    }

    public function setParams(array $params): static
    {
        foreach ($params as $param) {
            if ($param instanceof Param) {
                $this->params[] = $param;
            } elseif (is_array($param)) {
                $newParam = new Param(
                    $param['name'] ?? null,
                    $param['type'] ?? Param::TYPE_STRING,
                    $param['description'] ?? null
                );
                if (isset($param['required']) && $param['required'] === false) {
                    $newParam->optional();
                }
                if (isset($param['example'])) {
                    $newParam->setExample($param['example']);
                }
                $this->params[] = $newParam;
            }
        }
        return $this;
    }

    public function getGroup(): ?string
    {
        return $this->group;
    }

    public function setGroup(string $group): static
    {
        $this->group = $group;
        return $this;
    }

    public function getMethod(): string
    {
        return $this->method;
    }

    public function setMethod(string $method): static
    {
        $this->method = strtoupper($method);
        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(string $description): static
    {
        $this->description = $description;
        return $this;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;
        return $this;
    }

    public function setHeaders(array $headers): static
    {
        $this->headers = $headers;
        return $this;
    }

    public function addHeader(Param $header): static
    {
        $this->headers[] = $header;
        return $this;
    }

    public function setSuccessParams(array $params): static
    {
        $this->successParams = $params;
        return $this;
    }

    public function getRequestExample(): array
    {
        if (empty($this->requestExample) && !empty($this->params)) {
            $example = [];
            foreach ($this->params as $param) {
                if ($param instanceof Param) {
                    $name = $param->getName();
                    $example[$name] = $param->getExample() ?? $this->getExampleValue($param);
                }
            }
            return $example;
        }
        return $this->requestExample;
    }

    protected function getExampleValue(Param $param): mixed
    {
        $type = strtolower($param->getDataType());
        $name = strtolower($param->getName() ?? '');

        if (str_contains($name, 'email')) return 'user@example.com';
        if (str_contains($name, 'password')) return 'secret123';
        if (str_contains($name, 'name')) return 'John Doe';
        if (str_contains($name, 'phone')) return '+1234567890';
        if (str_contains($name, 'url')) return 'https://example.com';

        return match ($type) {
            'integer', 'int' => 1,
            'number', 'float', 'double' => 1.5,
            'boolean', 'bool' => true,
            'array' => [],
            default => 'string_value',
        };
    }

    public function setRequestExample(array $example): static
    {
        $this->requestExample = $example;
        return $this;
    }

    public function setSuccessExample(mixed $example, int $statusCode = 200, ?string $description = null): static
    {
        $this->successExamples[$statusCode] = [
            'description' => $description ?? 'Successful response',
            'example' => $example,
        ];
        return $this;
    }

    public function setErrorExample(mixed $example, int $statusCode = 400, ?string $description = null): static
    {
        $this->errorExamples[$statusCode] = [
            'description' => $description ?? $this->getDefaultErrorDescription($statusCode),
            'example' => $example,
        ];
        return $this;
    }

    protected function getDefaultErrorDescription(int $code): string
    {
        return match ($code) {
            400 => 'Bad Request',
            401 => 'Unauthorized',
            403 => 'Forbidden',
            404 => 'Not Found',
            422 => 'Validation Error',
            500 => 'Server Error',
            default => 'Error',
        };
    }

    public function getSuccessExamples(): array
    {
        return $this->successExamples;
    }

    public function getErrorExamples(): array
    {
        return $this->errorExamples;
    }

    public function getAllResponseExamples(): array
    {
        return array_merge($this->successExamples, $this->errorExamples);
    }

    public function withDefaultHeaders(): static
    {
        $headers = [];
        $headers[] = (new Param('Accept', Param::TYPE_STRING, 'Response content type'))
            ->setDefaultValue(self::CONSUME_JSON);

        $authHeaders = config('easy-doc.auth_headers', []);
        foreach ($authHeaders as $headerConfig) {
            $header = new Param(
                $headerConfig['name'],
                Param::TYPE_STRING,
                $headerConfig['description'] ?? ucfirst(str_replace(['-', '_'], ' ', $headerConfig['name']))
            );
            if (isset($headerConfig['example'])) {
                $header->setDefaultValue($headerConfig['example']);
            }
            if (isset($headerConfig['required']) && $headerConfig['required'] === false) {
                $header->optional();
            }
            $headers[] = $header;
        }

        $this->setHeaders($headers);
        return $this;
    }

    public function withConfigHeaders(array $headerNames): static
    {
        $authHeaders = config('easy-doc.auth_headers', []);

        foreach ($headerNames as $name) {
            foreach ($authHeaders as $headerConfig) {
                if ($headerConfig['name'] === $name) {
                    $header = new Param(
                        $headerConfig['name'],
                        Param::TYPE_STRING,
                        $headerConfig['description'] ?? ucfirst(str_replace(['-', '_'], ' ', $headerConfig['name']))
                    );
                    if (isset($headerConfig['example'])) {
                        $header->setDefaultValue($headerConfig['example']);
                    }
                    $this->addHeader($header);
                    break;
                }
            }
        }

        return $this;
    }

    public function getVersion(): string
    {
        return $this->version;
    }

    public function setVersion(string $version): static
    {
        $this->version = $version;
        return $this;
    }

    public function getDefine(): array
    {
        return $this->define;
    }

    public function setDefine(string $title, string $description = ''): static
    {
        $this->define = ['title' => $title, 'description' => $description];
        return $this;
    }

    public function getUse(): array
    {
        return $this->use;
    }

    public function setUse(string $definedName): static
    {
        $this->use[] = $definedName;
        return $this;
    }

    public function isAddDefaultHeaders(): bool
    {
        return $this->addDefaultHeaders;
    }

    public function noDefaultHeaders(): static
    {
        $this->addDefaultHeaders = false;
        return $this;
    }

    public function getHeaders(): array
    {
        return $this->headers;
    }

    public function getConsumes(): array
    {
        return $this->consumes;
    }

    public function setConsumes(array $consumes): static
    {
        $this->consumes = $consumes;
        return $this;
    }

    public function hasFileUploads(): bool
    {
        return in_array(self::CONSUME_MULTIPART, $this->consumes);
    }

    public function getSuccessObject(): mixed
    {
        return $this->successObject;
    }

    public function setSuccessObject(mixed $model): static
    {
        $this->successObject = $model;

        // If it's a class string, auto-register the schema
        if (is_string($model) && class_exists($model)) {
            $this->ensureSchemaExists($model);

            $reflection = new \ReflectionClass($model);
            $this->successSchema = $reflection->getShortName() . 'Response';
        }

        return $this;
    }

    /**
     * Set a list of objects as the success response.
     * Auto-generates schemas for the model and the collection wrapper.
     */
    public function setSuccessList(string $modelClass): static
    {
        if (class_exists($modelClass)) {
            $this->ensureSchemaExists($modelClass);

            $reflection = new \ReflectionClass($modelClass);
            $this->successSchema = $reflection->getShortName() . 'CollectionResponse';
        }

        return $this;
    }

    /**
     * Set a paginated list as the success response.
     * Auto-generates schemas for the model and the paginated wrapper.
     */
    public function setSuccessPaginated(string $modelClass): static
    {
        if (class_exists($modelClass)) {
            $this->ensureSchemaExists($modelClass);

            $reflection = new \ReflectionClass($modelClass);
            $this->successSchema = $reflection->getShortName() . 'PaginatedResponse';
        }

        return $this;
    }

    /**
     * Helper to ensure model schema and all its variants exist.
     */
    protected function ensureSchemaExists(string $modelClass): void
    {
        $reflection = new \ReflectionClass($modelClass);
        $schemaName = $reflection->getShortName();

        // 1. Create base model schema if missing
        if (!SchemaBuilder::has($schemaName)) {
            SchemaBuilder::fromModel($modelClass, $schemaName);
        }

        // 2. Ensure all response wrappers exist (Response, CollectionResponse, PaginatedResponse)
        SchemaBuilder::defineAllResponses($schemaName);
    }

    public function getSuccessPaginatedObject(): mixed
    {
        return $this->successPaginatedObject;
    }

    public function setSuccessPaginatedObject(mixed $model): static
    {
        $this->successPaginatedObject = $model;
        return $this;
    }

    public function setSuccessMessageOnly(): static
    {
        $this->successMessageOnly = true;
        return $this;
    }

    public function isSuccessMessageOnly(): bool
    {
        return $this->successMessageOnly;
    }

    public function getOperationId(): ?string
    {
        if ($this->operationId) {
            return $this->operationId;
        }
        $group = $this->group ?? 'api';
        $name = $this->name ?? 'operation';
        return lcfirst(str_replace(' ', '', ucwords($group))) . ucwords(str_replace(' ', '', $name));
    }

    public function setOperationId(string $operationId): static
    {
        $this->operationId = $operationId;
        return $this;
    }

    public function getSuccessParams(): array
    {
        return $this->successParams;
    }

    // =====================================================
    // Query Parameters
    // =====================================================

    /**
     * Set query parameters for this endpoint.
     */
    public function setQueryParams(array $params): static
    {
        foreach ($params as $param) {
            if ($param instanceof Param) {
                $param->setLocation(Param::LOCATION_QUERY);
                $this->queryParams[] = $param;
            }
        }
        return $this;
    }

    /**
     * Add a single query parameter.
     */
    public function addQueryParam(Param $param): static
    {
        $param->setLocation(Param::LOCATION_QUERY);
        $this->queryParams[] = $param;
        return $this;
    }

    /**
     * Get query parameters.
     */
    public function getQueryParams(): array
    {
        return $this->queryParams;
    }

    // =====================================================
    // Path Parameters
    // =====================================================

    /**
     * Auto-detect path parameters from the route.
     * Extracts {id}, {user}, etc.
     */
    public function autoDetectPathParams(): static
    {
        if (!$this->route) {
            return $this;
        }

        preg_match_all('/\{([^}]+)\}/', $this->route, $matches);

        foreach ($matches[1] as $paramName) {
            // Skip optional parameters marker
            $paramName = rtrim($paramName, '?');

            $param = new Param($paramName, Param::TYPE_STRING, ucfirst($paramName) . ' ID');
            $param->setLocation(Param::LOCATION_PATH);
            $this->pathParams[] = $param;
        }

        return $this;
    }

    /**
     * Get path parameters.
     */
    public function getPathParams(): array
    {
        return $this->pathParams;
    }

    /**
     * Add a path parameter manually.
     */
    public function addPathParam(Param $param): static
    {
        $param->setLocation(Param::LOCATION_PATH);
        $this->pathParams[] = $param;
        return $this;
    }

    // =====================================================
    // Tags / Categories
    // =====================================================

    /**
     * Set tags for grouping this endpoint.
     */
    public function setTags(array $tags): static
    {
        $this->tags = $tags;
        return $this;
    }

    /**
     * Get tags.
     */
    public function getTags(): array
    {
        // Fall back to group if no tags set
        if (empty($this->tags) && $this->group) {
            return [$this->group];
        }
        return $this->tags;
    }

    // =====================================================
    // Deprecation
    // =====================================================

    /**
     * Mark this endpoint as deprecated.
     */
    public function deprecated(?string $message = null): static
    {
        $this->deprecated = $message ?? 'This endpoint is deprecated.';
        return $this;
    }

    /**
     * Check if endpoint is deprecated.
     */
    public function isDeprecated(): bool
    {
        return $this->deprecated !== null;
    }

    /**
     * Get deprecation message.
     */
    public function getDeprecationMessage(): ?string
    {
        return $this->deprecated;
    }

    // =====================================================
    // Rate Limiting
    // =====================================================

    /**
     * Set rate limit for this endpoint.
     */
    public function rateLimit(int $limit, string $period = 'minute'): static
    {
        $this->rateLimit = [
            'limit' => $limit,
            'period' => $period,
        ];
        return $this;
    }

    /**
     * Get rate limit configuration.
     */
    public function getRateLimit(): ?array
    {
        return $this->rateLimit;
    }

    // =====================================================
    // Schema References
    // =====================================================

    /**
     * Set success response schema reference.
     */
    public function setSuccessSchema(string $schemaName): static
    {
        $this->successSchema = $schemaName;
        return $this;
    }

    /**
     * Get success schema name.
     */
    public function getSuccessSchema(): ?string
    {
        return $this->successSchema;
    }

    /**
     * Set error response schema reference.
     */
    public function setErrorSchema(string $schemaName): static
    {
        $this->errorSchema = $schemaName;
        return $this;
    }

    /**
     * Get error schema name.
     */
    public function getErrorSchema(): ?string
    {
        return $this->errorSchema;
    }

    public function getApiDoc(): string
    {
        $lines = [];
        $lines[] = "###";

        if (!empty($define = $this->getDefine())) {
            $lines[] = "@apiDefine {$define['title']} {$define['description']}";
        }

        $description = $this->getDescription();
        if (!empty($description)) {
            $lines[] = "@apiDescription {$description}";
        }

        $lines[] = "@apiVersion {$this->getVersion()}";
        $lines[] = "@api {{$this->getMethod()}} {$this->getRoute()} {$this->getName()}";
        $lines[] = "@apiGroup " . ucwords($this->getGroup() ?? 'General');

        foreach ($this->params as $param) {
            if ($param instanceof Param) {
                $fieldName = $param->getName();
                if (empty($fieldName)) {
                    throw new \Exception('The parameters requires a fieldname');
                }
                if (!$param->getRequired()) {
                    $fieldName = '[' . $fieldName . ']';
                }
                $lines[] = "@apiParam {{$param->getDataType()}} {$fieldName} {$param->getDescription()}";
            }
        }

        foreach ($this->successParams as $param) {
            if ($param instanceof Param) {
                $fieldName = $param->getName();
                if (empty($fieldName)) {
                    throw new \Exception('The parameters requires a fieldname');
                }
                if (!$param->getRequired()) {
                    $fieldName = '[' . $fieldName . ']';
                }
                $lines[] = "@apiSuccess {{$param->getDataType()}} {$fieldName} {$param->getDescription()}";
            }
        }

        foreach ($this->headers as $param) {
            if ($param instanceof Param) {
                $fieldName = $param->getName();
                if (empty($fieldName)) {
                    throw new \Exception('The parameters requires a fieldname');
                }
                if (!$param->getRequired()) {
                    $fieldName = '[' . $fieldName . ']';
                }
                $lines[] = "@apiHeader {{$param->getDataType()}} {$fieldName} {$param->getDescription()}";
            }
        }

        foreach ($this->use as $use) {
            $lines[] = "@apiUse $use";
        }

        $requestExampleParams = $this->getRequestExample();
        if (!empty($requestExampleParams)) {
            $lines[] = "@apiParamExample {json} Request Example ";
            $lines[] = json_encode($requestExampleParams, JSON_PRETTY_PRINT);
        }

        foreach ($this->successExamples as $code => $data) {
            $lines[] = "@apiSuccessExample {json} {$code} {$data['description']}";
            $lines[] = json_encode($data['example'], JSON_PRETTY_PRINT);
        }

        foreach ($this->errorExamples as $code => $data) {
            $lines[] = "@apiErrorExample {json} {$code} {$data['description']}";
            $lines[] = json_encode($data['example'], JSON_PRETTY_PRINT);
        }

        $lines[] = "###";

        return implode("\r\n", $lines);
    }
}
