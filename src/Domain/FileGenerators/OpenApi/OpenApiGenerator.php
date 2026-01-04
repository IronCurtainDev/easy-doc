<?php

namespace EasyDoc\Domain\FileGenerators\OpenApi;

use EasyDoc\Contracts\GeneratorInterface;
use EasyDoc\Domain\FileGenerators\OpenApi\OpenApiSchema;
use EasyDoc\Services\OpenApiConverter;
use Illuminate\Support\Collection;

class OpenApiGenerator implements GeneratorInterface
{
    protected OpenApiConverter $converter;

    public function __construct(OpenApiConverter $converter)
    {
        $this->converter = $converter;
    }

    public function generate(Collection $apiCalls, string $outputDir): array
    {
        $openApiConfig = new OpenApiSchema();
        $basePath = config('easy-doc.base_path', '/api/v1');

        $openApiConfig->setBasePath($basePath);
        $openApiConfig->setServerUrl(config('app.url') . $basePath, 'Current Server');

        // Add defined schemas
        $schemas = \EasyDoc\Docs\SchemaBuilder::all();
        foreach ($schemas as $name => $schema) {
            $openApiConfig->addSchema($name, $schema);
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
            if ($item->getRateLimit()) {
                $rateLimit = $item->getRateLimit();
                $description .= "\n\n**Rate Limit:** {$rateLimit['limit']} requests per {$rateLimit['period']}";
            }
            if ($item->isDeprecated()) {
                $description = "**DEPRECATED:** {$item->getDeprecationMessage()}\n\n" . $description;
            }

            // Possible Errors
            $possibleErrors = $item->getPossibleErrors();
            if (!empty($possibleErrors)) {
                $description .= "\n\n**Possible Errors:**\n";
                foreach ($possibleErrors as $code => $desc) {
                    $description .= "- `{$code}`: {$desc}\n";
                }
            }

            $pathData = [
                'tags' => $item->getTags(),
                'summary' => $item->getName(),
                'description' => $description,
                'operationId' => $item->getOperationId(),
                'parameters' => $parameters,
                'security' => $openApiConfig->getSecuritySchemes(),
                'responses' => $this->converter->buildResponses($item),
            ];

            if ($item->isDeprecated()) {
                $pathData['deprecated'] = true;
            }

            $openApiConfig->addPathData($pathSuffix, $method, $pathData);
        }

        $generatedFiles = [];

        $jsonPath = $outputDir . DIRECTORY_SEPARATOR . 'openapi.json';
        $openApiConfig->writeOutputFileJson($jsonPath);
        $generatedFiles['OpenAPI 3.0 (JSON)'] = $jsonPath;

        $yamlPath = $outputDir . DIRECTORY_SEPARATOR . 'openapi.yml';
        $openApiConfig->writeOutputFileYaml($yamlPath);
        $generatedFiles['OpenAPI 3.0 (YAML)'] = $yamlPath;

        return $generatedFiles;
    }
}
