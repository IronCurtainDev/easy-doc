<?php

declare(strict_types=1);

namespace EasyDoc\Domain\FileGenerators\Markdown;

use EasyDoc\Docs\APICall;
use EasyDoc\Docs\SchemaBuilder;
use EasyDoc\Domain\CodeExamples\CurlGenerator;
use EasyDoc\Domain\CodeExamples\FetchGenerator;
use Illuminate\Support\Collection;

/**
 * Generate GitHub-style Markdown API documentation.
 */
class MarkdownGenerator implements \EasyDoc\Contracts\GeneratorInterface
{
    protected string $title;
    protected string $description;
    protected string $baseUrl;
    protected string $version;
    protected Collection $endpoints;
    protected bool $includeCurl = true;
    protected bool $includeFetch = true;

    public function __construct()
    {
        $config = config('easy-doc', []);
        $this->title = $config['api_info']['title'] ?? config('app.name', 'API') . ' Documentation';
        $this->description = $config['api_info']['description'] ?? 'API Documentation';
        $this->version = $config['api_info']['version'] ?? '1.0.0';
        $this->baseUrl = config('app.url', 'http://localhost') . ($config['base_path'] ?? '/api/v1');
        $this->endpoints = collect();
    }

    /**
     * Set whether to include curl examples.
     */
    public function includeCurl(bool $include = true): static
    {
        $this->includeCurl = $include;
        return $this;
    }

    /**
     * Set whether to include fetch examples.
     */
    public function includeFetch(bool $include = true): static
    {
        $this->includeFetch = $include;
        return $this;
    }

    /**
     * Generate markdown documentation files.
     */
    public function generate(Collection $apiCalls, string $outputDir): array
    {
        $this->endpoints = $apiCalls;

        $md = $this->generateHeader();
        $md .= $this->generateTableOfContents();
        $md .= $this->generateEndpoints();
        $md .= $this->generateSchemas();

        $outputPath = $outputDir . DIRECTORY_SEPARATOR . 'API.md';
        $this->saveFile($outputPath, $md);

        return ['Markdown Docs' => $outputPath];
    }

    /**
     * Generate header section.
     */
    protected function generateHeader(): string
    {
        $md = "# {$this->title}\n\n";
        $md .= "> {$this->description}\n\n";
        $md .= "**Version:** `{$this->version}`  \n";
        $md .= "**Base URL:** `{$this->baseUrl}`\n\n";
        $md .= "---\n\n";

        return $md;
    }

    /**
     * Generate table of contents.
     */
    protected function generateTableOfContents(): string
    {
        $md = "## Table of Contents\n\n";

        // Group by tag
        $grouped = $this->endpoints->groupBy(fn($e) => $e->getGroup() ?? 'General');

        foreach ($grouped as $group => $endpoints) {
            $md .= "- [{$group}](#{$this->slugify($group)})\n";
            foreach ($endpoints as $endpoint) {
                $name = $endpoint->getName();
                $method = strtoupper($endpoint->getMethod());
                $md .= "  - [{$method}] [{$name}](#{$this->slugify($name)})\n";
            }
        }

        $md .= "\n---\n\n";

        return $md;
    }

    /**
     * Generate endpoints section.
     */
    protected function generateEndpoints(): string
    {
        $md = "## Endpoints\n\n";

        $grouped = $this->endpoints->groupBy(fn($e) => $e->getGroup() ?? 'General');

        foreach ($grouped as $group => $endpoints) {
            $md .= "### {$group}\n\n";

            foreach ($endpoints as $endpoint) {
                $md .= $this->generateEndpoint($endpoint);
            }
        }

        return $md;
    }

    /**
     * Generate single endpoint documentation.
     */
    protected function generateEndpoint(APICall $endpoint): string
    {
        $name = $endpoint->getName();
        $method = strtoupper($endpoint->getMethod());
        $route = $endpoint->getRoute();
        $description = $endpoint->getDescription();

        $md = "#### {$name}\n\n";
        $md .= "```http\n{$method} {$route}\n```\n\n";

        if ($description) {
            $md .= "{$description}\n\n";
        }

        // Headers
        $headers = $endpoint->getHeaders();
        if (!empty($headers)) {
            $md .= "**Headers:**\n\n";
            $md .= "| Name | Type | Required | Description |\n";
            $md .= "|------|------|----------|-------------|\n";
            foreach ($headers as $header) {
                $required = $header->getRequired() ? '✅' : '❌';
                $md .= "| `{$header->getName()}` | {$header->getDataType()} | {$required} | {$header->getDescription()} |\n";
            }
            $md .= "\n";
        }

        // Parameters
        $params = $endpoint->getParams();
        if (!empty($params)) {
            $md .= "**Parameters:**\n\n";
            $md .= "| Name | Type | Required | Description |\n";
            $md .= "|------|------|----------|-------------|\n";
            foreach ($params as $param) {
                $required = $param->getRequired() ? '✅' : '❌';
                $desc = $param->getDescription() ?? '';
                $md .= "| `{$param->getName()}` | {$param->getDataType()} | {$required} | {$desc} |\n";
            }
            $md .= "\n";
        }

        // Request example
        $requestExample = $endpoint->getRequestExample();
        if (!empty($requestExample)) {
            $md .= "**Request Body:**\n\n";
            $md .= "```json\n" . json_encode($requestExample, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n```\n\n";
        }

        // Success Response
        $successExamples = $endpoint->getSuccessExamples();
        if (!empty($successExamples)) {
            $md .= "**Success Response:**\n\n";
            foreach ($successExamples as $example) {
                $code = $example['code'] ?? 200;
                $description = $example['description'] ?? 'Success';
                $md .= "`{$code}` {$description}\n\n";
                if (isset($example['data'])) {
                    $md .= "```json\n" . json_encode($example['data'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n```\n\n";
                }
            }
        }

        // Error Response
        $errorExamples = $endpoint->getErrorExamples();
        if (!empty($errorExamples)) {
            $md .= "**Error Response:**\n\n";
            foreach ($errorExamples as $example) {
                $code = $example['code'] ?? 400;
                $description = $example['description'] ?? 'Error';
                $md .= "`{$code}` {$description}\n\n";
                if (isset($example['data'])) {
                    $md .= "```json\n" . json_encode($example['data'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n```\n\n";
                }
            }
        }

        // Possible Errors
        $possibleErrors = $endpoint->getPossibleErrors();
        if (!empty($possibleErrors)) {
            $md .= "**Possible Errors:**\n\n";
            $md .= "| Code | Description |\n";
            $md .= "|------|-------------|\n";
            foreach ($possibleErrors as $code => $description) {
                $md .= "| `{$code}` | {$description} |\n";
            }
            $md .= "\n";
        }

        // Code Examples
        if ($this->includeCurl || $this->includeFetch) {
            $md .= "**Code Examples:**\n\n";

            // Build headers for examples
            $exampleHeaders = ['Content-Type' => 'application/json', 'Accept' => 'application/json'];
            foreach ($headers as $header) {
                $exampleHeaders[$header->getName()] = $header->getExample() ?? '{{' . $header->getName() . '}}';
            }

            $fullUrl = $this->baseUrl . '/' . ltrim($route, '/');

            if ($this->includeCurl) {
                $curl = CurlGenerator::generate($method, $fullUrl, $exampleHeaders, $requestExample);
                $md .= "<details>\n<summary>curl</summary>\n\n```bash\n{$curl}\n```\n\n</details>\n\n";
            }

            if ($this->includeFetch) {
                $fetch = FetchGenerator::generate($method, $fullUrl, $exampleHeaders, $requestExample);
                $md .= "<details>\n<summary>JavaScript (fetch)</summary>\n\n```javascript\n{$fetch}\n```\n\n</details>\n\n";
            }
        }

        $md .= "---\n\n";

        return $md;
    }

    /**
     * Generate schemas section.
     */
    protected function generateSchemas(): string
    {
        $schemas = SchemaBuilder::all();
        if (empty($schemas)) {
            return '';
        }

        $md = "## Schemas\n\n";

        foreach ($schemas as $name => $schema) {
            // Skip response wrappers
            if (str_ends_with($name, 'Response') || str_ends_with($name, 'CollectionResponse')) {
                continue;
            }

            $md .= "### {$name}\n\n";
            $md .= "| Property | Type | Required | Description |\n";
            $md .= "|----------|------|----------|-------------|\n";

            $properties = $schema['properties'] ?? [];
            $required = $schema['required'] ?? [];

            foreach ($properties as $propName => $propDef) {
                $type = $propDef['type'] ?? 'string';
                if (isset($propDef['$ref'])) {
                    $type = str_replace('#/definitions/', '', $propDef['$ref']);
                }
                $isRequired = in_array($propName, $required) ? '✅' : '❌';
                $desc = $propDef['description'] ?? '';

                $md .= "| `{$propName}` | {$type} | {$isRequired} | {$desc} |\n";
            }

            $md .= "\n";
        }

        return $md;
    }

    /**
     * Create a URL-friendly slug.
     */
    protected function slugify(?string $text): string
    {
        return strtolower(preg_replace('/[^a-zA-Z0-9]+/', '-', $text ?? ''));
    }

    /**
     * Save content to file.
     */
    protected function saveFile(string $path, string $content): void
    {
        $directory = dirname($path);
        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }
        file_put_contents($path, $content);
    }
}
