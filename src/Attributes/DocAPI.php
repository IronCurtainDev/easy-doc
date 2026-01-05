<?php

declare(strict_types=1);

namespace EasyDoc\Attributes;

use Attribute;

/**
 * Main attribute for documenting API endpoints.
 * Apply to controller methods to define API documentation.
 *
 * @example
 * #[DocAPI(
 *     name: 'Login User',
 *     group: 'Authentication',
 *     description: 'Authenticate user with email and password',
 *     successObject: User::class
 * )]
 * public function login(Request $request) { ... }
 */
#[Attribute(Attribute::TARGET_METHOD)]
class DocAPI
{
    public function __construct(
        /**
         * Name of the API endpoint.
         */
        public ?string $name = null,

        /**
         * Group/category for the endpoint (e.g., 'Authentication', 'Users').
         */
        public ?string $group = null,

        /**
         * Detailed description of what the endpoint does.
         */
        public ?string $description = null,

        /**
         * Model class for success response schema (e.g., User::class).
         */
        public ?string $successObject = null,

        /**
         * Model class for paginated success response.
         */
        public ?string $successPaginatedObject = null,

        /**
         * Whether the response is just a message (no payload).
         */
        public bool $successMessageOnly = false,

        /**
         * Custom operation ID for OpenAPI.
         */
        public ?string $operationId = null,

        /**
         * API version (e.g., '1.0.0').
         */
        public string $version = '0.2.0',

        /**
         * Tags for additional categorization.
         */
        public array $tags = [],

        /**
         * Deprecation message (null if not deprecated).
         */
        public ?string $deprecated = null,

        /**
         * Rate limit (e.g., ['limit' => 60, 'period' => 'minute']).
         */
        public ?array $rateLimit = null,

        /**
         * Content types this endpoint consumes.
         */
        public array $consumes = [],

        /**
         * Whether to add default headers from config.
         */
        public bool $addDefaultHeaders = true,

        /**
         * Header names from config to include (alternative to DocHeader attributes).
         */
        public array $headers = [],

        /**
         * Request body parameters as arrays (alternative to DocParam attributes).
         * Each array: ['name' => 'email', 'type' => 'string', 'description' => '...', 'example' => '...']
         */
        public array $params = [],

        /**
         * Request example data.
         */
        public array $requestExample = [],

        /**
         * Success response parameters.
         * Each array: ['name' => 'token', 'type' => 'string', 'description' => 'Auth token']
         */
        public array $successParams = [],

        /**
         * Define a reusable documentation block.
         * Format: ['title' => 'block_name', 'description' => 'Block description']
         */
        public ?array $define = null,

        /**
         * Use/reference a defined documentation block.
         * Can be a string (single) or array (multiple).
         */
        public string|array $use = [],

        /**
         * List of possible error codes/messages.
         * Format: [400 => 'Bad Request', 401 => 'Unauthorized', ...]
         */
        public array $possibleErrors = [],

        /**
         * Custom success response schema name reference.
         */
        public ?string $successSchema = null,

        /**
         * Custom error response schema name reference.
         */
        public ?string $errorSchema = null,
    ) {}
}
