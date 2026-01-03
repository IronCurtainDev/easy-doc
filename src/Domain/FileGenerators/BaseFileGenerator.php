<?php

namespace EasyDoc\Domain\FileGenerators;

use Illuminate\Support\Facades\File;
use Symfony\Component\Yaml\Yaml;

/**
 * Base class for file generation.
 */
abstract class BaseFileGenerator
{
    protected array $schema = [];

    public function getSchema(): array
    {
        return $this->schema;
    }

    public function setSchema(array $schema): static
    {
        $this->schema = $schema;
        return $this;
    }

    public function addToSchema(string $key, mixed $value): static
    {
        $this->schema[$key] = $value;
        return $this;
    }

    /**
     * Write schema to JSON file.
     */
    public function writeOutputFileJson(string $filePath): void
    {
        $directory = dirname($filePath);
        if (!File::isDirectory($directory)) {
            File::makeDirectory($directory, 0755, true);
        }

        File::put($filePath, json_encode($this->schema, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }

    /**
     * Write schema to YAML file.
     */
    public function writeOutputFileYaml(string $filePath): void
    {
        $directory = dirname($filePath);
        if (!File::isDirectory($directory)) {
            File::makeDirectory($directory, 0755, true);
        }

        File::put($filePath, Yaml::dump($this->schema, 10, 2, Yaml::DUMP_EMPTY_ARRAY_AS_SEQUENCE));
    }
}
