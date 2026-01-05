<?php

declare(strict_types=1);

use EasyDoc\Docs\DocBuilder;
use EasyDoc\Exceptions\DocumentationModeEnabledException;

if (!function_exists('document')) {
    /**
     * Register API documentation for an endpoint.
     *
     * @param callable $callback A callback that returns an APICall instance
     * @return void
     * @throws DocumentationModeEnabledException In documentation mode
     */
    function document(callable $callback): void
    {
        if (!env('DOCUMENTATION_MODE', false)) {
            return;
        }

        /** @var DocBuilder $docBuilder */
        $docBuilder = app('easy-doc.builder');

        $apiCall = $callback();
        $docBuilder->register($apiCall);
        $docBuilder->throwDocumentationModeException();
    }
}

if (!function_exists('easy_doc_config')) {
    /**
     * Get easy-doc configuration value.
     *
     * @param string|null $key
     * @param mixed $default
     * @return mixed
     */
    function easy_doc_config(?string $key = null, mixed $default = null): mixed
    {
        if ($key === null) {
            return config('easy-doc');
        }

        return config("easy-doc.{$key}", $default);
    }
}

if (!function_exists('type')) {
    /**
     * Create a SchemaType for defining extra API columns in models.
     *
     * Usage in models:
     * ```php
     * public function addExtraAPIColumns(): array
     * {
     *     return [
     *         'access_token' => type('string')->example('eyJ0eXAi...'),
     *         'places' => type('array')->of(Place::class),
     *         'profile' => type('object')->model(Profile::class),
     *     ];
     * }
     * ```
     *
     * @param string $type The base type (string, integer, boolean, array, object)
     * @return \EasyDoc\Docs\SchemaType
     */
    function type(string $type = 'string'): \EasyDoc\Docs\SchemaType
    {
        return new \EasyDoc\Docs\SchemaType($type);
    }
}
