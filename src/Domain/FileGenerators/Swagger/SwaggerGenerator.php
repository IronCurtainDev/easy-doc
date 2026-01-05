<?php

declare(strict_types=1);

namespace EasyDoc\Domain\FileGenerators\Swagger;

use EasyDoc\Contracts\GeneratorInterface;
use EasyDoc\Domain\FileGenerators\Swagger\SwaggerV2;
use EasyDoc\Services\OpenApiConverter;
use Illuminate\Support\Collection;

class SwaggerGenerator implements GeneratorInterface
{
    protected OpenApiConverter $converter;

    public function __construct(OpenApiConverter $converter)
    {
        $this->converter = $converter;
    }

    public function generate(Collection $apiCalls, string $outputDir): array
    {
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
                'consumes' => ['application/json'],
                'produces' => ['application/json'],
                'parameters' => $parameters,
                'security' => $swaggerConfig->getSecuritySchemes(),
                'responses' => $this->converter->buildResponses($item),
            ];

            if ($item->isDeprecated()) {
                $pathData['deprecated'] = true;
            }

            if ($item->getRateLimit()) {
                $pathData['x-rateLimit'] = $item->getRateLimit();
            }

            $swaggerConfig->addPathData($pathSuffix, $method, $pathData);
        }

        $generatedFiles = [];

        $jsonPath = $outputDir . DIRECTORY_SEPARATOR . 'swagger.json';
        $swaggerConfig->writeOutputFileJson($jsonPath);
        $generatedFiles['Swagger v2 (JSON)'] = $jsonPath;

        $yamlPath = $outputDir . DIRECTORY_SEPARATOR . 'swagger.yml';
        $swaggerConfig->writeOutputFileYaml($yamlPath);
        $generatedFiles['Swagger v2 (YAML)'] = $yamlPath;

        return $generatedFiles;
    }
}
