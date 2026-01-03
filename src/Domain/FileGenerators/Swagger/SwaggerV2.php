<?php

namespace EasyDoc\Domain\FileGenerators\Swagger;

use EasyDoc\Domain\FileGenerators\BaseFileGenerator;

/**
 * Swagger 2.0 specification generator.
 */
class SwaggerV2 extends BaseFileGenerator
{
    protected string $basePath = '/api/v1';
    protected string $serverUrl = '';

    public function __construct()
    {
        $this->initializeSchema();
    }

    protected function initializeSchema(): void
    {
        $config = config('easy-doc', []);

        $this->schema = [
            'swagger' => '2.0',
            'info' => [
                'title' => $config['api_info']['title'] ?? config('app.name', 'API') . ' Documentation',
                'description' => $config['api_info']['description'] ?? 'API Documentation',
                'version' => $config['api_info']['version'] ?? '1.0.0',
            ],
            'host' => '',
            'basePath' => $config['base_path'] ?? '/api/v1',
            'schemes' => ['https', 'http'],
            'consumes' => ['application/json'],
            'produces' => ['application/json'],
            'paths' => [],
            'securityDefinitions' => $this->buildSecurityDefinitions(),
            'definitions' => [],
        ];
    }

    /**
     * Build security definitions from configured auth headers.
     */
    protected function buildSecurityDefinitions(): array
    {
        $definitions = [];
        $authHeaders = config('easy-doc.auth_headers', []);

        foreach ($authHeaders as $headerConfig) {
            $schemeName = $headerConfig['security_scheme'] ?? $this->generateSecuritySchemeName($headerConfig['name']);
            $type = $headerConfig['type'] ?? 'api_key';

            if ($type === 'bearer' || $type === 'http') {
                $definitions[$schemeName] = [
                    'type' => 'apiKey',
                    'name' => $headerConfig['name'],
                    'in' => 'header',
                    'description' => $headerConfig['description'] ?? $headerConfig['name'],
                ];
            } else {
                $definitions[$schemeName] = [
                    'type' => 'apiKey',
                    'name' => $headerConfig['name'],
                    'in' => 'header',
                    'description' => $headerConfig['description'] ?? $headerConfig['name'],
                ];
            }
        }

        return $definitions;
    }

    /**
     * Generate a camelCase security scheme name from header name.
     */
    protected function generateSecuritySchemeName(string $headerName): string
    {
        // Convert x-api-key to apiKey, Authorization to authorization, etc.
        $name = str_replace(['x-', 'X-'], '', $headerName);
        $name = str_replace(['-', '_'], ' ', $name);
        $name = ucwords($name);
        $name = str_replace(' ', '', $name);
        return lcfirst($name);
    }

    public function setBasePath(string $basePath): static
    {
        $this->basePath = $basePath;
        $this->schema['basePath'] = $basePath;
        return $this;
    }

    public function setServerUrl(string $serverUrl): static
    {
        $this->serverUrl = $serverUrl;

        // Parse the URL to get host
        $parsed = parse_url($serverUrl);
        if (isset($parsed['host'])) {
            $host = $parsed['host'];
            if (isset($parsed['port'])) {
                $host .= ':' . $parsed['port'];
            }
            $this->schema['host'] = $host;

            // Set schemes based on URL
            if (isset($parsed['scheme'])) {
                $this->schema['schemes'] = [$parsed['scheme']];
            }
        }

        return $this;
    }

    public function addPathData(string $path, string $method, array $pathData): static
    {
        // Normalize path (ensure it starts with /)
        if (!str_starts_with($path, '/')) {
            $path = '/' . $path;
        }

        // Remove base path from the path if present
        $path = str_replace($this->basePath, '', $path);
        if (!str_starts_with($path, '/')) {
            $path = '/' . $path;
        }

        if (!isset($this->schema['paths'][$path])) {
            $this->schema['paths'][$path] = [];
        }

        $this->schema['paths'][$path][strtolower($method)] = $pathData;

        return $this;
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
}
