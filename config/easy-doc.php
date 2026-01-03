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

    // Base path for API routes
    'base_path' => '/api/v1',

    /*
    |--------------------------------------------------------------------------
    | Authentication Headers Configuration
    |--------------------------------------------------------------------------
    |
    | Define your authentication headers here. You can use any header names
    | that match your API's authentication scheme.
    |
    | Supported types:
    | - 'api_key': For static API keys (e.g., x-api-key, api-key, Authorization)
    | - 'bearer': For bearer tokens (e.g., x-access-token, Authorization)
    | - 'custom': For any custom header type
    |
    | Security scheme options (for Swagger):
    | - 'apiKey': API key in header
    | - 'http': HTTP authentication (bearer, basic)
    | - 'oauth2': OAuth 2.0
    |
    */
    'auth_headers' => [
        // Example: API Key header
        [
            'name' => 'x-api-key',           // Your header name (can be anything)
            'type' => 'api_key',             // Type: api_key, bearer, custom
            'description' => 'API Key for authentication',
            'required' => true,
            'security_scheme' => 'apiKey',   // Swagger security scheme name
            'example' => '{{x-api-key}}',    // Example value for Postman
        ],

        // Example: Access Token header (uncomment to enable)
        // [
        //     'name' => 'x-access-token',
        //     'type' => 'bearer',
        //     'description' => 'User authentication token',
        //     'required' => false,
        //     'security_scheme' => 'accessToken',
        //     'example' => '{{x-access-token}}',
        // ],
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

        // Route path for the documentation viewer
        'route' => 'easy-doc',

        // Middleware to apply to the viewer route
        'middleware' => ['web'],
    ],
];
