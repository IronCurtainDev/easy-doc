<?php

declare(strict_types=1);

namespace EasyDoc\Domain\FileGenerators\Postman;

use EasyDoc\Domain\FileGenerators\BaseFileGenerator;

/**
 * Postman Collection generator.
 */
class PostmanCollectionBuilder extends BaseFileGenerator
{
    protected array $items = [];
    protected array $variables = [];

    public function __construct()
    {
        $this->initializeSchema();
    }

    protected function initializeSchema(): void
    {
        $config = config('easy-doc', []);

        $this->schema = [
            'info' => [
                '_postman_id' => $this->generateUuid(),
                'name' => $config['api_info']['title'] ?? config('app.name', 'API') . ' Collection',
                'description' => $config['api_info']['description'] ?? 'API Collection',
                'schema' => 'https://schema.getpostman.com/json/collection/v2.1.0/collection.json',
            ],
            'item' => [],
            'variable' => $this->buildVariables(),
        ];
    }

    /**
     * Build collection variables from configured auth headers.
     */
    protected function buildVariables(): array
    {
        $variables = [
            [
                'key' => 'base_url',
                'value' => config('app.url', 'http://localhost'),
                'type' => 'string',
            ],
        ];

        // Add auth header variables
        $authHeaders = config('easy-doc.auth_headers', []);
        foreach ($authHeaders as $headerConfig) {
            $varName = str_replace(['-', ' '], '_', strtolower($headerConfig['name']));
            $variables[] = [
                'key' => $varName,
                'value' => $headerConfig['example'] ?? '',
                'type' => 'string',
            ];
        }

        return $variables;
    }

    /**
     * Generate a UUID for Postman collection.
     */
    protected function generateUuid(): string
    {
        return sprintf(
            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff)
        );
    }

    /**
     * Build from Swagger schema.
     */
    public function buildFromSwagger(array $swaggerSchema): static
    {
        $basePath = $swaggerSchema['basePath'] ?? '/api/v1';
        $host = $swaggerSchema['host'] ?? '{{base_url}}';
        $scheme = $swaggerSchema['schemes'][0] ?? 'https';

        $groupedItems = [];

        foreach ($swaggerSchema['paths'] ?? [] as $path => $methods) {
            foreach ($methods as $method => $pathData) {
                $tag = $pathData['tags'][0] ?? 'General';

                if (!isset($groupedItems[$tag])) {
                    $groupedItems[$tag] = [
                        'name' => $tag,
                        'item' => [],
                    ];
                }

                $request = $this->buildRequest(
                    $method,
                    $scheme . '://' . $host . $basePath . $path,
                    $pathData
                );

                $groupedItems[$tag]['item'][] = [
                    'name' => $pathData['summary'] ?? $path,
                    'request' => $request,
                    'response' => [],
                ];
            }
        }

        $this->schema['item'] = array_values($groupedItems);

        return $this;
    }

    /**
     * Build a Postman request from path data.
     */
    protected function buildRequest(string $method, string $url, array $pathData): array
    {
        $headers = $this->buildHeaders();
        $body = null;

        // Build query parameters
        $query = [];
        $bodyParams = [];

        foreach ($pathData['parameters'] ?? [] as $param) {
            $location = $param['in'] ?? 'query';

            if ($location === 'query') {
                $query[] = [
                    'key' => $param['name'],
                    'value' => $param['schema']['example'] ?? '',
                    'description' => $param['description'] ?? '',
                ];
            } elseif (in_array($location, ['formData', 'body'])) {
                $bodyParams[$param['name']] = $param['example'] ?? '';
            }
        }

        if (!empty($bodyParams)) {
            $body = [
                'mode' => 'raw',
                'raw' => json_encode($bodyParams, JSON_PRETTY_PRINT),
                'options' => [
                    'raw' => [
                        'language' => 'json',
                    ],
                ],
            ];
        }

        // Parse URL
        $parsedUrl = parse_url($url);
        $urlObj = [
            'raw' => str_replace(['http://', 'https://'], '{{base_url}}/', preg_replace('/^https?:\/\/[^\/]+/', '', $url)),
            'host' => ['{{base_url}}'],
            'path' => array_filter(explode('/', $parsedUrl['path'] ?? '')),
        ];

        if (!empty($query)) {
            $urlObj['query'] = $query;
        }

        $request = [
            'method' => strtoupper($method),
            'header' => $headers,
            'url' => $urlObj,
        ];

        if ($body !== null) {
            $request['body'] = $body;
        }

        return $request;
    }

    /**
     * Build headers from configuration.
     */
    protected function buildHeaders(): array
    {
        $headers = [
            [
                'key' => 'Accept',
                'value' => 'application/json',
                'type' => 'text',
            ],
            [
                'key' => 'Content-Type',
                'value' => 'application/json',
                'type' => 'text',
            ],
        ];

        // Add auth headers from config
        $authHeaders = config('easy-doc.auth_headers', []);
        foreach ($authHeaders as $headerConfig) {
            $varName = str_replace(['-', ' '], '_', strtolower($headerConfig['name']));
            $headers[] = [
                'key' => $headerConfig['name'],
                'value' => '{{' . $varName . '}}',
                'type' => 'text',
                'description' => $headerConfig['description'] ?? '',
            ];
        }

        return $headers;
    }

    public function addVariable(string $key, mixed $value): static
    {
        $this->variables[$key] = $value;
        $this->schema['variable'][] = [
            'key' => $key,
            'value' => $value,
            'type' => 'string',
        ];
        return $this;
    }
}
