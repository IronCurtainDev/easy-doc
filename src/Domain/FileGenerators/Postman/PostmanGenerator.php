<?php

declare(strict_types=1);

namespace EasyDoc\Domain\FileGenerators\Postman;

use EasyDoc\Contracts\GeneratorInterface;
use EasyDoc\Domain\FileGenerators\Swagger\SwaggerV2;
use EasyDoc\Services\OpenApiConverter;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;

class PostmanGenerator implements GeneratorInterface
{
    protected OpenApiConverter $converter;

    public function __construct(OpenApiConverter $converter)
    {
        $this->converter = $converter;
    }

    public function generate(Collection $apiCalls, string $outputDir): array
    {
        // 1. Build Swagger Structure (Intermediate)
        $swaggerConfig = new SwaggerV2();
        $basePath = config('easy-doc.base_path', '/api/v1');
        $swaggerConfig->setBasePath($basePath);
        $swaggerConfig->setServerUrl(config('app.url'));

        // Add defined schemas
        $schemas = \EasyDoc\Docs\SchemaBuilder::all();
        foreach ($schemas as $name => $schema) {
            $swaggerConfig->addDefinition($name, $schema);
        }

        foreach ($apiCalls as $item) {
            $route = $item->getRoute();
            if (empty($route) || !empty($item->getDefine())) {
                continue;
            }
            $method = strtolower($item->getMethod());
            $parameters = $this->converter->buildParameters($item, $method);
            $pathSuffix = str_replace(ltrim($basePath, '/'), '', $route);

            // Build description
            $description = $item->getDescription() ?? '';
            // (Omitting full description logic for brevity in intermediate step, key is structure)

            $pathData = [
                'tags' => $item->getTags(),
                'summary' => $item->getName(),
                'description' => $description,
                'operationId' => $item->getOperationId(),
                'consumes' => ['application/json'],
                'produces' => ['application/json'],
                'parameters' => $parameters,
                'responses' => $this->converter->buildResponses($item),
            ];
            $swaggerConfig->addPathData($pathSuffix, $method, $pathData);
        }

        // 2. Convert to Postman
        $swaggerSchema = $swaggerConfig->getSchema();
        $postmanBuilder = new PostmanCollectionBuilder();
        $postmanBuilder->buildFromSwagger($swaggerSchema);

        $collectionPath = $outputDir . DIRECTORY_SEPARATOR . 'postman_collection.json';
        $postmanBuilder->writeOutputFileJson($collectionPath);

        // 3. Generate Environment
        $envPath = $this->generateEnvironment($outputDir);

        return [
            'Postman Collection' => $collectionPath,
            'Postman Environment' => $envPath,
        ];
    }

    protected function generateEnvironment(string $outputDir): string
    {
        $authHeaders = config('easy-doc.auth_headers', []);
        $appUrl = config('app.url', 'http://localhost:8000');

        $environment = [
            'id' => 'easy-doc-env-' . time(),
            'name' => config('app.name', 'API') . ' Environment',
            'values' => [
                [
                    'key' => 'base_url',
                    'value' => $appUrl . config('easy-doc.base_path', '/api/v1'),
                    'type' => 'default',
                    'enabled' => true,
                ],
            ],
            '_postman_variable_scope' => 'environment',
        ];

        foreach ($authHeaders as $header) {
            $environment['values'][] = [
                'key' => str_replace(['-', '_'], '_', $header['name']),
                'value' => $header['example'] ?? '',
                'type' => 'secret',
                'enabled' => true,
            ];
        }

        $outputPath = $outputDir . DIRECTORY_SEPARATOR . 'postman_environment.json';
        if (!is_dir(dirname($outputPath))) {
            mkdir(dirname($outputPath), 0755, true);
        }

        File::put($outputPath, json_encode($environment, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        return $outputPath;
    }
}
