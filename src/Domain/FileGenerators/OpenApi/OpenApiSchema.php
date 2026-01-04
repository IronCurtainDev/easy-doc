<?php

namespace EasyDoc\Domain\FileGenerators\OpenApi;

use EasyDoc\Domain\FileGenerators\BaseFileGenerator;

/**
 * OpenAPI 3.0 specification generator.
 */
class OpenApiSchema extends BaseFileGenerator
{
    protected string $basePath = '/api/v1';

    public function __construct()
    {
        $this->initializeSchema();
    }

    protected function initializeSchema(): void
    {
        $config = config('easy-doc', []);

        $this->schema = [
            'openapi' => '3.0.3',
            'info' => [
                'title' => $config['api_info']['title'] ?? config('app.name', 'API') . ' Documentation',
                'description' => $config['api_info']['description'] ?? 'API Documentation',
                'version' => $config['api_info']['version'] ?? '1.0.0',
            ],
            'servers' => [],
            'paths' => [],
            'components' => [
                'securitySchemes' => $this->buildSecuritySchemes(),
                'schemas' => [],
            ],
        ];
    }

    /**
     * Build security schemes from configured auth headers.
     */
    protected function buildSecuritySchemes(): array
    {
        $schemes = [];
        $authHeaders = config('easy-doc.auth_headers', []);

        foreach ($authHeaders as $headerConfig) {
            $schemeName = $headerConfig['security_scheme'] ?? $this->generateSecuritySchemeName($headerConfig['name']);
            $type = $headerConfig['type'] ?? 'api_key';

            if ($type === 'bearer') {
                $schemes[$schemeName] = [
                    'type' => 'http',
                    'scheme' => 'bearer',
                    'bearerFormat' => 'JWT',
                    'description' => $headerConfig['description'] ?? $headerConfig['name'],
                ];
            } else {
                // API Key type
                $schemes[$schemeName] = [
                    'type' => 'apiKey',
                    'in' => 'header',
                    'name' => $headerConfig['name'],
                    'description' => $headerConfig['description'] ?? $headerConfig['name'],
                ];
            }
        }

        return $schemes;
    }

    /**
     * Generate a camelCase security scheme name from header name.
     */
    protected function generateSecuritySchemeName(string $headerName): string
    {
        $name = str_replace(['x-', 'X-'], '', $headerName);
        $name = str_replace(['-', '_'], ' ', $name);
        $name = ucwords($name);
        $name = str_replace(' ', '', $name);
        return lcfirst($name);
    }

    public function setBasePath(string $basePath): static
    {
        $this->basePath = $basePath;
        return $this;
    }

    public function setServerUrl(string $url, string $description = 'API Server'): static
    {
        $this->schema['servers'] = [
            [
                'url' => $url,
                'description' => $description,
            ],
        ];
        return $this;
    }

    public function addServer(string $url, string $description = ''): static
    {
        $this->schema['servers'][] = [
            'url' => $url,
            'description' => $description,
        ];
        return $this;
    }

    public function addPathData(string $path, string $method, array $pathData): static
    {
        // Normalize path
        if (!str_starts_with($path, '/')) {
            $path = '/' . $path;
        }

        $path = str_replace($this->basePath, '', $path);
        if (!str_starts_with($path, '/')) {
            $path = '/' . $path;
        }

        if (!isset($this->schema['paths'][$path])) {
            $this->schema['paths'][$path] = [];
        }

        // Convert Swagger 2.0 format to OpenAPI 3.0
        $openApiPathData = $this->convertToOpenApi3($pathData, $method);

        $this->schema['paths'][$path][strtolower($method)] = $openApiPathData;

        return $this;
    }

    /**
     * Convert Swagger 2.0 path data to OpenAPI 3.0 format.
     */
    protected function convertToOpenApi3(array $pathData, string $method): array
    {
        $openApiData = [
            'tags' => $pathData['tags'] ?? [],
            'summary' => $pathData['summary'] ?? '',
            'description' => $pathData['description'] ?? '',
            'operationId' => $pathData['operationId'] ?? null,
            'security' => $pathData['security'] ?? [],
            'responses' => [],
        ];

        // Convert parameters
        $parameters = [];
        $requestBody = null;

        foreach ($pathData['parameters'] ?? [] as $param) {
            $location = $param['in'] ?? 'query';

            if (in_array($location, ['formData', 'body'])) {
                // Move to requestBody
                if ($requestBody === null) {
                    $requestBody = [
                        'content' => [
                            'application/json' => [
                                'schema' => [
                                    'type' => 'object',
                                    'properties' => [],
                                    'required' => [],
                                ],
                            ],
                        ],
                    ];
                }

                $requestBody['content']['application/json']['schema']['properties'][$param['name']] = [
                    'type' => $param['type'] ?? 'string',
                    'description' => $param['description'] ?? '',
                ];

                if ($param['required'] ?? false) {
                    $requestBody['content']['application/json']['schema']['required'][] = $param['name'];
                }
            } else {
                $parameters[] = [
                    'name' => $param['name'],
                    'in' => $location,
                    'required' => $param['required'] ?? false,
                    'description' => $param['description'] ?? '',
                    'schema' => [
                        'type' => $param['type'] ?? 'string',
                    ],
                ];
            }
        }

        if (!empty($parameters)) {
            $openApiData['parameters'] = $parameters;
        }

        if ($requestBody !== null) {
            $openApiData['requestBody'] = $requestBody;
        }

        // Convert responses
        foreach ($pathData['responses'] ?? [] as $statusCode => $response) {
            $openApiData['responses'][(string)$statusCode] = [
                'description' => $response['description'] ?? '',
            ];

            if (isset($response['schema'])) {
                $openApiData['responses'][(string)$statusCode]['content'] = [
                    'application/json' => [
                        'schema' => $this->convertSchemaRef($response['schema']),
                    ],
                ];
            }
        }

        // Default response if none defined
        if (empty($openApiData['responses'])) {
            $openApiData['responses']['200'] = [
                'description' => 'Successful response',
            ];
        }

        return $openApiData;
    }

    /**
     * Convert Swagger 2.0 $ref to OpenAPI 3.0 format recursively.
     */
    protected function convertSchemaRef(array $schema): array
    {
        return $this->convertRefsRecursive($schema);
    }

    /**
     * Recursively convert all #/definitions/ refs to #/components/schemas/.
     */
    protected function convertRefsRecursive(array $data): array
    {
        foreach ($data as $key => $value) {
            if ($key === '$ref' && is_string($value)) {
                // Convert #/definitions/Name to #/components/schemas/Name
                $data[$key] = str_replace('#/definitions/', '#/components/schemas/', $value);
            } elseif (is_array($value)) {
                // Recursively process nested arrays
                $data[$key] = $this->convertRefsRecursive($value);
            }
        }

        return $data;
    }

    /**
     * Get configured security scheme names.
     */
    public function getSecuritySchemes(): array
    {
        $schemes = [];
        $authHeaders = config('easy-doc.auth_headers', []);

        foreach ($authHeaders as $headerConfig) {
            $schemeName = $headerConfig['security_scheme'] ?? $this->generateSecuritySchemeName($headerConfig['name']);
            $schemes[] = [$schemeName => []];
        }

        return $schemes;
    }

    /**
     * Add a schema to components with ref conversion.
     */
    public function addSchema(string $name, array $schema): static
    {
        // Convert all refs from Swagger 2.0 format to OpenAPI 3.0 format
        $convertedSchema = $this->convertRefsRecursive($schema);
        $this->schema['components']['schemas'][$name] = $convertedSchema;
        return $this;
    }
}
