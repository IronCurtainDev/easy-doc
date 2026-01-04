<?php

return [
    /*
    |--------------------------------------------------------------------------
    | API Documentation Settings
    |--------------------------------------------------------------------------
    |
    | Configure your API documentation generation settings here.
    |
    */

    // API Info for Swagger/OpenAPI
    'api_info' => [
        'title' => env('APP_NAME', 'API') . ' Documentation',
        'description' => 'API Documentation',
        'version' => '1.0.0',
    ],

    // Base path for API routes (Optional)
    // Used only for Swagger basePath display. Actual routes are read from your api.php.
    // Set to null or empty string if you don't want a basePath in Swagger spec.
    'base_path' => '/api/v1',

    /*
    |--------------------------------------------------------------------------
    | Authentication Headers Configuration (Optional)
    |--------------------------------------------------------------------------
    |
    | This is OPTIONAL. Define reusable authentication headers here if you want
    | to reference them in your API documentation using withConfigHeaders().
    |
    | If you prefer, you can define headers directly in each endpoint instead.
    |
    | Supported types:
    | - 'api_key': For static API keys (e.g., x-api-key, api-key)
    | - 'bearer': For bearer tokens (e.g., x-access-token)
    | - 'custom': For any custom header type
    |
    | Example configuration:
    | [
    |     'name' => 'x-api-key',
    |     'type' => 'api_key',
    |     'description' => 'API Key for authentication',
    |     'required' => true,
    |     'security_scheme' => 'apiKey',
    |     'example' => '{{x-api-key}}',
    | ],
    |
    */
    'auth_headers' => [
        // Add your reusable auth headers here if needed
    ],

    /*
    |--------------------------------------------------------------------------
    | Default Headers
    |--------------------------------------------------------------------------
    |
    | Headers that are always included in documentation.
    |
    */
    'default_headers' => [
        [
            'name' => 'Accept',
            'value' => 'application/json',
            'description' => 'Response content type',
        ],
        [
            'name' => 'Content-Type',
            'value' => 'application/json',
            'description' => 'Request content type',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Output Settings
    |--------------------------------------------------------------------------
    |
    | Configure where documentation files are generated.
    |
    */
    'output' => [
        'path' => public_path('docs'),
        'formats' => ['swagger2', 'openapi3', 'postman'], // Available: swagger2, openapi3, postman

        // TypeScript Type Generation
        'typescript' => [
            'enabled' => true,
            'file' => 'types.ts',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Model Auto-Discovery
    |--------------------------------------------------------------------------
    |
    | Automatically discover Eloquent models and register them as schemas.
    | This allows you to use them in TypeScript generation without manual setup.
    |
    */
    'auto_discover_models' => true,
    'model_path' => [
        app_path('Models'),
        // app_path('Domain/User/Models'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Server Configuration
    |--------------------------------------------------------------------------
    |
    | Define API servers for documentation.
    |
    */
    'servers' => [
        [
            'url' => env('APP_URL', 'http://localhost'),
            'description' => 'Current Server',
        ],
        // Add sandbox/staging server if needed
        // [
        //     'url' => env('APP_SANDBOX_URL'),
        //     'description' => 'Sandbox Server',
        // ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Global Response Wrapper
    |--------------------------------------------------------------------------
    |
    | Define a global wrapper for your API responses.
    | Use '__DATA__' as a placeholder for the actual response data.
    | Set to null to disable wrapping.
    |
    | Example:
    | 'response_wrapper' => [
    |     'success' => true,
    |     'message' => 'Success',
    |     'data' => '__DATA__'
    | ],
    */
    'response_wrapper' => null,

    /*
    |--------------------------------------------------------------------------
    | Response Structure Configuration
    |--------------------------------------------------------------------------
    |
    | Configure the keys used in standardized API responses.
    |
    */
    'response' => [
        'keys' => [
            'result' => 'result',   // Boolean status
            'message' => 'message', // Standard message
            'data' => 'payload',    // Main data/payload
            'errors' => 'errors',   // Validation errors
            'meta' => 'meta',       // Pagination meta
            'links' => 'links',     // Pagination links
            'code' => 'code',       // Error code (optional)
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Documentation Viewer
    |--------------------------------------------------------------------------
    |
    | Configure the web-based documentation viewer.
    | Visit /easy-doc to see all your documentation files.
    |
    */
    'viewer' => [
        // Enable/disable the documentation viewer route
        // Set EASY_DOC_VISIBLE=true in your .env to enable
        'enabled' => env('EASY_DOC_VISIBLE', false),
        'route' => 'easy-doc', // The dashboard
        'public_route' => 'api-docs', // The beautiful Redoc page

        // Route path for the documentation viewer


        // Middleware to apply to the viewer route
        'middleware' => ['web'],
    ],
];
