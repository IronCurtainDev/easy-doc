# Easy-Doc

A lightweight Laravel package for API documentation generation with **fully configurable headers** and **built-in Swagger UI**.

## Features

- **Configurable Headers** - Define any header names (api-key, x-access-token, etc.)
- **Swagger 2.0** - Generate Swagger 2.0 specification (JSON/YAML)
- **OpenAPI 3.0** - Generate OpenAPI 3.0 specification (JSON/YAML)
- **Swagger UI** - Interactive documentation interface out of the box
- **Postman Collection** - Generate Postman collection with variables
- **Documentation Viewer** - Browse all generated docs at `/easy-doc`
- **Lightweight** - Just documentation, no auth/models/CRUD

## Installation

### 1. Require Package

```bash
composer require iron-curtain/easy-doc
```

### 2. Auto-Install

Run the installer to configure your API name and base URL automatically:

```bash
php artisan easy-doc:install
```

### 3. Generate Documentation

You can generate documentation immediately with zero configuration:

```bash
php artisan easy-doc:generate --auto
```

That's it! Your documentation is now available at `/easy-doc`.

## Quick Start

### 1. Publish Configuration

```bash
php artisan vendor:publish --tag=easy-doc-config
```

### 2. Enable the Documentation Viewer

Add to your `.env` file:

```env
EASY_DOC_VISIBLE=true
```

### 3. Configure Your Headers (Optional)

If you want to define reusable authentication headers, edit `config/easy-doc.php`:

```php
'auth_headers' => [
    [
        'name' => 'x-api-key',
        'type' => 'api_key',
        'description' => 'API Key for authentication',
        'required' => true,
        'security_scheme' => 'apiKey',
        'example' => '{{x-api-key}}',
    ],
],
```

> **Note:** This step is optional. You can document APIs without configuring global auth headers.

### 4. Document Your Endpoints

In your controllers, use the `document()` helper:

```php
use EasyDoc\Docs\APICall;
use EasyDoc\Docs\Param;

public function register(Request \)
{
    document(function () {
        return (new APICall())
            ->setName('Register')
            ->setDescription('Register a new user')
            ->withConfigHeaders(['api-key'])
            ->setParams([
                new Param('name', 'string', 'User name'),
                new Param('email', 'string', 'User email'),
                new Param('password', 'string', 'User password'),
            ]);
    });

    // Your actual logic...
}

public function logout(Request \)
{
    document(function () {
        return (new APICall())
            ->setName('Logout')
            ->setDescription('Logout and invalidate token')
            ->withConfigHeaders(['api-key', 'x-access-token'])
            ->setParams([]);
    });

    // Your logout logic...
}
```

### 5. Generate Documentation

```bash
php artisan easy-doc:generate
```

## Viewing Documentation

After generation, access your docs:

| URL                             | Description                    |
| ------------------------------- | ------------------------------ |
| `/easy-doc`                     | Documentation viewer dashboard |
| `/docs/index.html`              | Interactive Swagger UI         |
| `/docs/swagger.json`            | Swagger 2.0 JSON               |
| `/docs/openapi.json`            | OpenAPI 3.0 JSON               |
| `/docs/postman_collection.json` | Postman Collection             |

## Generated Files

| File                                  | Description                   |
| ------------------------------------- | ----------------------------- |
| `public/docs/index.html`              | **Interactive Swagger UI**    |
| `public/docs/swagger.json`            | Swagger 2.0 specification     |
| `public/docs/swagger.yml`             | Swagger 2.0 YAML              |
| `public/docs/openapi.json`            | OpenAPI 3.0 specification     |
| `public/docs/openapi.yml`             | OpenAPI 3.0 YAML              |
| `public/docs/postman_collection.json` | Postman Collection            |
| `public/docs/api/index.html`          | ApiDoc HTML (requires apidoc) |

## Param Class Usage

Create parameters using the constructor:

```php
// Basic usage: Param(name, type, description)
new Param('email', 'string', 'User email address')

// Make optional (default is required)
(new Param('page', 'integer', 'Page number'))->optional()

// Set default value
(new Param('limit', 'integer', 'Items per page'))->setDefaultValue(10)
```

**Available Types:**

- `string` - Text values
- `integer` - Whole numbers
- `number` - Decimal numbers
- `boolean` - True/false
- `array` - Arrays
- `file` - File uploads

## 🪄 Magic Methods (Auto-Schema)

Forget defining schemas manually! Just pass your Eloquent model:

```php
// 1. Single Object Response
->setSuccessObject(User::class)
// Auto-generates User schema and returns { success: true, data: {...} }

// 2. List Response
->setSuccessList(User::class)
// Auto-generates User schema and returns { success: true, data: [{...}, {...}] }

// 3. Paginated Response
->setSuccessPaginated(User::class)
// Auto-generates User schema and returns { success: true, data: [...], meta: {...}, links: {...} }
```

You can still use `SchemaBuilder::defineResource()` if you need to customize relationships or fields first.

You can still use `SchemaBuilder::defineResource()` if you need to customize relationships or fields first.

## 🤖 Smart Automation

Easy-Doc works hard so you don't have to. If you use **FormRequest** validation and standard naming conventions, you can skip almost everything!

### 1. Auto-FormRequest Parsing

If your controller method uses a `FormRequest`, we automatically extract validation rules and document them as parameters.

**Code:**

```php
// In StoreUserRequest
public function rules() {
    return [
        'email' => 'required|email|unique:users',
        'age' => 'integer|min:18',
        'role' => 'in:admin,user',
    ];
}

// In UserController
public function store(StoreUserRequest $request)
{
    // Minimal doc definition
    document(fn() => (new APICall())->setSuccessObject(User::class));
}
```

**Result:**

- **Params:** `email` (required), `age` (min: 18), `role` (enum: admin, user)
- **Response:** User object
- **Name:** "Create a User" (Auto-inferred from method name)
- **Group:** "User" (Auto-inferred from controller name)

## Parameter Validation

Add validation constraints to your parameters:

```php
// Enum values (shows as dropdown in Swagger UI)
(new Param('status', 'string', 'User status'))
    ->enum(['active', 'inactive', 'pending'])

// Min/max constraints
(new Param('age', 'integer', 'User age'))
    ->min(18)
    ->max(120)

// Regex pattern
(new Param('phone', 'string', 'Phone number'))
    ->pattern('^\+[0-9]{10,15}$')
```

## Query Parameters

Separate query parameters from body parameters:

```php
->setQueryParams([
    (new Param('page', 'integer', 'Page number'))->optional()->defaultValue(1),
    (new Param('per_page', 'integer', 'Items per page'))->optional()->defaultValue(15),
    (new Param('sort', 'string', 'Sort order'))->enum(['asc', 'desc'])->optional(),
])
```

## Tags & Categories

Group your endpoints with tags:

```php
->setTags(['Authentication', 'Public API'])
```

## Deprecation

Mark endpoints as deprecated:

```php
->deprecated('Use /api/v2/users instead')
```

Deprecated endpoints show with strikethrough in Swagger UI.

## Rate Limiting

Document rate limits for your endpoints:

```php
->rateLimit(60, 'minute')  // 60 requests per minute
->rateLimit(1000, 'hour')  // 1000 requests per hour
```

## Path Parameters

Auto-detect path parameters from your route:

```php
// Route: /api/v1/users/{id}
->autoDetectPathParams()  // Automatically documents {id} parameter

// Or add manually
->addPathParam(new Param('id', 'integer', 'User ID'))
```

## Reusable Schemas

Define schemas once, use them everywhere:

```php
use EasyDoc\Docs\SchemaBuilder;

// Define in a service provider or config
SchemaBuilder::define('User', [
    'id' => 'integer',
    'name' => 'string',
    'email' => 'string',
    'created_at' => 'string',
]);

// Use predefined helpers
SchemaBuilder::defineErrorResponse();
SchemaBuilder::defineSuccessResponse();

// Reference in your endpoints
->setSuccessSchema('User')
```

## Response Examples

Document what your API returns so frontend developers know exactly what to expect:

```php
->setSuccessExample([
    'access_token' => 'eyJ0eXAiOiJKV1Q...',
    'token_type' => 'Bearer',
    'user' => [
        'id' => 1,
        'name' => 'John Doe',
        'email' => 'user@example.com',
    ],
], 201, 'User registered successfully')

->setErrorExample([
    'message' => 'The email has already been taken.',
    'errors' => [
        'email' => ['The email has already been taken.'],
    ],
], 422, 'Validation error')
```

**Parameters:**

- `$example` - The response body (array or object)
- `$statusCode` - HTTP status code (default: 200 for success, 400 for error)
- `$description` - Optional description for the response

## Postman Environment

Easy-doc automatically generates a Postman environment file with your configured variables:

**Generated file:** `public/docs/postman_environment.json`

**Includes:**

- `base_url` - Your API base URL
- All configured auth headers (e.g., `api_key`, `x_access_token`)

Import both the collection and environment into Postman to start testing immediately!

## Selective Header Authentication

Use `->withConfigHeaders()` to add headers to specific endpoints:

```php
// Public endpoint - only api-key
->withConfigHeaders(['api-key'])

// Protected endpoint - api-key + access token
->withConfigHeaders(['api-key', 'x-access-token'])
```

Headers added via `withConfigHeaders()` are marked as **required** in the documentation.

## Command Options

```bash
# Generate all formats (default)
php artisan easy-doc:generate

# Generate only Swagger 2.0
php artisan easy-doc:generate --format=swagger2

# Generate only OpenAPI 3.0
php artisan easy-doc:generate --format=openapi3

# Reset and regenerate
php artisan easy-doc:generate --reset

# Skip ApiDoc HTML generation
php artisan easy-doc:generate --no-apidoc
```

## Configuration Options

```php
return [
    // API Info
    'api_info' => [
        'title' => env('APP_NAME', 'API') . ' Documentation',
        'description' => 'API Documentation',
        'version' => '1.0.0',
    ],

    // Base path for API routes
    'base_path' => '/api/v1',

    // Auth headers (see above)
    'auth_headers' => [...],

    // Output settings
    'output' => [
        'path' => public_path('docs'),
        'formats' => ['swagger2', 'openapi3', 'postman'],
    ],

    // Documentation viewer
    'viewer' => [
        'enabled' => env('EASY_DOC_VISIBLE', false),
        'route' => 'easy-doc',
        'middleware' => ['web'],
    ],
];
```

## 🔮 "Alive" Documentation

### Smart Examples (Faker)

When you use `SchemaBuilder::fromModel(User::class)`, we automatically inspect your database schema and generate **realistic example data** for your documentation using standard Faker formatters.

- `email` field -> Generates "jane.doe@example.com"
- `phone` field -> Generates "+1-202-555-0109"

### Global Response Wrappers

Most APIs wrap their responses (e.g., `{ "success": true, "data": ... }`).
Configure it once in `config/easy-doc.php`:

```php
'response_wrapper' => [
    'success' => true,
    'code' => 200,
    'result' => '__DATA__', // Values are injected here
],
```

Now, `setSuccessObject(User::class)` will implicitly use this wrapper structure.

### Bridge to Frontend (TypeScript)

Automatically generate TypeScript interfaces for your API responses.

1. Run `php artisan easy-doc:generate`
2. Check `public/docs/types.ts`
3. Import them in your frontend: `import { UserResponse } from './types';`

### Model Auto-Discovery 🕵️‍♂️

EasyDoc automatically finds all your Eloquent models in `app/Models` and registers them for documentation.

- No need to manually call `SchemaBuilder::fromModel()`.
- Disabling this: Set `'auto_discover_models' => false` in `config/easy-doc.php`.

## Requirements

- PHP 8.2+
- Laravel 11+
- (Optional) `apidoc` for HTML docs: `npm install -g apidoc`

## License

MIT License
