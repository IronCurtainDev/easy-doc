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
        'version' => '0.2.0',
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
    | Parameter Templates (Reusable)
    |--------------------------------------------------------------------------
    |
    | Define reusable parameter templates that can be referenced by name
    | in your DocParam attributes using the 'template' property.
    |
    | Example usage in controller:
    | #[DocParam(template: 'email')]
    | #[DocParam(template: 'password')]
    |
    */
    'param_templates' => [
        'email' => [
            'type' => 'string',
            'description' => 'Email address',
            'example' => 'user@example.com',
            'required' => true,
            'pattern' => '^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$',
        ],
        'password' => [
            'type' => 'string',
            'description' => 'Password',
            'example' => 'secret123',
            'required' => true,
            'min' => 8,
        ],
        'name' => [
            'type' => 'string',
            'description' => 'Full name',
            'example' => 'John Doe',
            'required' => true,
            'min' => 2,
            'max' => 255,
        ],
        'page' => [
            'type' => 'integer',
            'description' => 'Page number for pagination',
            'example' => 1,
            'required' => false,
            'location' => 'query',
        ],
        'per_page' => [
            'type' => 'integer',
            'description' => 'Number of items per page',
            'example' => 15,
            'required' => false,
            'location' => 'query',
            'min' => 1,
            'max' => 100,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Error Response Presets
    |--------------------------------------------------------------------------
    |
    | Define common error responses that can be referenced by name
    | using the DocError attribute or 'errorPreset' in DocResponse.
    |
    | Example usage in controller:
    | #[DocError('validation')]
    | #[DocError('unauthenticated')]
    |
    */
    'error_presets' => [
        'validation' => [
            'status' => 422,
            'description' => 'Validation Error',
            'example' => [
                'result' => false,
                'message' => 'The given data was invalid.',
                'errors' => ['field' => ['The field is required.']],
            ],
        ],
        'unauthenticated' => [
            'status' => 401,
            'description' => 'Unauthenticated',
            'example' => [
                'result' => false,
                'message' => 'Unauthenticated.',
            ],
        ],
        'unauthorized' => [
            'status' => 403,
            'description' => 'Unauthorized',
            'example' => [
                'result' => false,
                'message' => 'You are not authorized to perform this action.',
            ],
        ],
        'not_found' => [
            'status' => 404,
            'description' => 'Not Found',
            'example' => [
                'result' => false,
                'message' => 'Resource not found.',
            ],
        ],
        'rate_limit' => [
            'status' => 429,
            'description' => 'Too Many Requests',
            'example' => [
                'result' => false,
                'message' => 'Too many requests. Please try again later.',
            ],
        ],
        'server_error' => [
            'status' => 500,
            'description' => 'Server Error',
            'example' => [
                'result' => false,
                'message' => 'An unexpected error occurred.',
            ],
        ],
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
