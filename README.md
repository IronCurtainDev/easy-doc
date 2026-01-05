# EasyDoc 📚

[![Packagist Version](https://img.shields.io/packagist/v/ironcurtaindev/easy-doc)](https://packagist.org/packages/ironcurtaindev/easy-doc) [![Total Downloads](https://img.shields.io/packagist/dt/ironcurtaindev/easy-doc)](https://packagist.org/packages/ironcurtaindev/easy-doc)

A lightweight, developer-friendly API documentation generator for Laravel.

**Stop writing YAML manually.** `EasyDoc` auto-generates beautiful Markdown documentation, OpenAPI (Swagger) specs, Postman collections, and even a fully typed TypeScript SDK directly from your Laravel codebase using a fluent, expressive API.

---

## 🚀 Features

-   **Fluent API**: Define documentation directly in your Controller logic using `document()` function.
-   **PHP 8 Attributes**: Alternatively, use `#[DocAPI]`, `#[DocParam]`, `#[DocHeader]`, `#[DocResponse]` attributes for cleaner code.
-   **Automatic Schema Discovery**: Eloquent models are automatically scanned.
-   **Mobile Ready**: Generated **OpenAPI 3.0** & **Swagger 2.0** specs are perfect for generating **iOS (Swift)** and **Android (Kotlin)** clients via generic code generators.
-   **Multi-Format Output**: Markdown, OpenAPI 3.0, Swagger 2.0, Postman, TypeScript SDK.
-   **Configurable Headers**: Define global authentication headers once in your config.

---

## 🎯 Why Easy-Doc?

### 🏢 For Teams: The "Bus Factor" Solution

If your backend developer leaves, does the next person know how the API works?
With `Easy-Doc`, documentation lives **inside the code**.

-   **Knowledge Transfer**: The docs are right next to the logic.
-   **Self-Explaining Code**: The Fluent API (`->name('Login')`) makes intent clear.

### 🛡️ Real-World Resilience

Projects get paused. Clients change requirements. Developers changes.

-   **Project Restarts**: Paused for 6 months? Since docs are code, they don't "rot". You pick up exactly where you left off.
-   **Change Requests**: When a client changes a requirement, you change the code AND the doc in the same file. No desync. No "I forgot to update the wiki".

### 🧠👨‍💻 For Solo Devs: Your "External Brain"

Working alone? `Easy-Doc` acts as your memory.

-   **Completeness Check**: By explicitly defining endpoints, you instantly spot missing descriptions or edge cases.
-   **Future-Proofing**: Come back to your project 6 months later and know _exactly_ what every endpoint does without re-reading the execution logic.

**It bridges the gap between "Code" and "Explanation".**

---

## 📦 Installation

Install via Composer:

### Stable Version (Recommended)

```bash
composer require ironcurtaindev/easy-doc:^0.2
```

### Development Version (Bleeding Edge)

```bash
composer require ironcurtaindev/easy-doc:dev-main
```

Publish the configuration (Optional):

```bash
php artisan vendor:publish --provider="EasyDoc\EasyDocServiceProvider"
```

---

## ⚙️ Configuration (Auto-Discovery)

### Model Auto-Discovery

By default, `EasyDoc` scans your `app/Models` directory.
You just need to ensure your models are standard Eloquent models.

```php
// config/easy-doc.php
'auto_discover_models' => true,
'model_path' => app_path('Models'),
```

### Reusable Authentication Headers

Define headers that appear frequently across your API. Then reference them by name in your endpoints.

```php
// config/easy-doc.php
'auth_headers' => [
    [
        'name' => 'x-api-key',
        'type' => 'api_key',
        'description' => 'API Key for authentication',
        'required' => true,
        'example' => '{{x-api-key}}',
    ],
    [
        'name' => 'x-access-token',
        'type' => 'bearer',
        'description' => 'JWT access token',
        'required' => true,
        'example' => 'eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9...',
    ],
],
```

Then reference them in your attributes:

```php
#[DocAPI(
    name: 'Get User Profile',
    headers: ['x-api-key', 'x-access-token']  // Uses config headers
)]
```

Or with the `document()` function:

```php
->setHeaders(['x-api-key', 'x-access-token'])
// or
->withConfigHeaders(['x-api-key', 'x-access-token'])
```

### Default Headers

Headers included in ALL endpoints automatically:

```php
// config/easy-doc.php
'default_headers' => [
    ['name' => 'Accept', 'value' => 'application/json', 'description' => 'Response content type'],
    ['name' => 'Content-Type', 'value' => 'application/json', 'description' => 'Request content type'],
],
```

> **Tip:** Use `addDefaultHeaders: false` in `#[DocAPI]` to skip default headers for a specific endpoint.

---

## 📖 Usage Guide

Since schemas are auto-discovered, you focus purely on **Documenting Endpoints**.

**Scenario**: A **User** has one **Partner** and many **Places**.

### 1. Document Your Endpoints 📝

Use the `document()` helper in your Controllers.

#### Scenario: User Registration (Auth)

```php
// AuthController.php

public function register(Request $request) {
    document(function() {
        return (new APICall())
            ->setName('Register User')
            ->setGroup('Authentication')
            ->setParams([
                Param::make('name', Param::TYPE_STRING, 'Full name')->required()->example('John Doe'),
                Param::make('email', Param::TYPE_STRING, 'Email address')->required()->example('john@example.com'),
                Param::make('password', Param::TYPE_STRING, 'Password (min 8 chars)')->required(),
                Param::make('password_confirmation', Param::TYPE_STRING, 'Confirm Password')->required(),
            ])
            ->setSuccessMessageOnly('User created successfully')
            ->setSuccessExample(['token' => 'abc...'], 201, 'User created');
    });

    // ... Your validation and logic
}
```

#### Scenario: User Partner (One-to-One)

Demonstrates using `setSuccessObject` to automatically document a model response.

```php
// PartnerController.php

public function show(Request $request) {
    document(function() {
        return (new APICall())
            ->setName('Get Partner')
            ->setGroup('Partner')
            ->addHeader(
                Param::header('Authorization', 'Bearer token')->example('Bearer eyJ...')
            )
            // Automatically documents the response based on the Partner model schema
            ->setSuccessObject(Partner::class)
            ->setErrorExample(['result' => false, 'message' => 'Not found'], 404, 'No partner found');
    });

    // ... logic
}
```

#### Scenario: User Places (One-to-Many & Pagination)

Demonstrates `setSuccessPaginatedObject` for paginated responses.

```php
// PlaceController.php

public function index(Request $request) {
    document(function() {
        return (new APICall())
            ->setName('List Places')
            ->setGroup('Places')
            ->addHeader(Param::header('Authorization', 'Bearer token'))
            // Documents a paginated list of Place models
            ->setSuccessPaginatedObject(Place::class)
            ->setSuccessExample([/* ... example JSON ... */], 200, 'Places list');
    });

    $places = $request->user()->places()->paginate(10);
    return response()->apiSuccessPaginated($places);
}
```

---

### Alternative: PHP 8 Attributes 🏷️

> **New in v0.3!** You can now define documentation using PHP 8 Attributes instead of the `document()` function. This keeps your documentation metadata outside the function body for cleaner code.

#### Benefits of Attributes:

-   **Cleaner controller methods** - Business logic is separated from documentation
-   **IDE support** - Better autocomplete and validation
-   **Standard PHP pattern** - Follows modern PHP 8+ conventions
-   **Compile-time validation** - PHP validates attribute syntax
-   **Full feature parity** - All `document()` options available as attributes

#### Example: Login with Attributes

```php
use EasyDoc\Attributes\DocAPI;
use EasyDoc\Attributes\DocParam;
use EasyDoc\Attributes\DocHeader;
use EasyDoc\Attributes\DocResponse;

#[DocAPI(
    name: 'Login User',
    group: 'Authentication',
    description: 'Authenticate user with email and password, returns access token',
    successObject: User::class,
    tags: ['auth', 'login'],
    possibleErrors: [401 => 'Unauthorized', 422 => 'Validation Error']
)]
#[DocHeader(name: 'api_key', description: 'API Key for authentication')]
#[DocHeader(name: 'x-access-token', description: 'Access token', required: false)]
#[DocParam(name: 'email', type: 'string', description: 'User email address', example: 'john@example.com')]
#[DocParam(name: 'password', type: 'string', description: 'User password', example: 'secret123')]
#[DocResponse(
    status: 200,
    description: 'Login successful',
    example: ['result' => true, 'message' => 'Login successful', 'payload' => ['token' => 'eyJ...']]
)]
#[DocResponse(
    status: 422,
    description: 'Invalid credentials',
    example: ['result' => false, 'message' => 'The provided credentials are incorrect.'],
    isError: true
)]
public function login(Request $request)
{
    // Only business logic here - no documentation code!
    $validated = $request->validate([
        'email' => 'required|string|email',
        'password' => 'required|string',
    ]);

    // ... authentication logic
}
```

#### Available Attributes

| Attribute             | Purpose                             | Repeatable |
| --------------------- | ----------------------------------- | ---------- |
| `#[DocAPI(...)]`      | Main endpoint documentation         | No         |
| `#[DocParam(...)]`    | Request body/query/path parameters  | Yes        |
| `#[DocHeader(...)]`   | Request headers                     | Yes        |
| `#[DocResponse(...)]` | Success and error response examples | Yes        |
| `#[DocRequest(...)]`  | Auto-document FormRequest rules     | No         |

#### 🆕 Auto-Documenting FormRequests with `#[DocRequest]`

Stop repeating yourself! If you use Laravel's `FormRequest` for validation, you can automatically generate documentation parameters from your rules.

```php
use EasyDoc\Attributes\DocRequest;
use App\Http\Requests\RegisterRequest;

#[DocAPI(name: 'Register', group: 'Auth')]
#[DocRequest(RegisterRequest::class)] // <--- Magic happens here!
public function register(RegisterRequest $request)
{
    // ...
}
```

`EasyDoc` parses the `rules()` method and converts them into `#[DocParam]` entries automatically, including types and required status.

#### DocAPI Options (Complete Reference)

```php
#[DocAPI(
    // Basic Information
    name: 'Login User',                      // Endpoint name
    group: 'Authentication',                 // Group/category
    description: 'Authenticate user...',     // Detailed description
    version: '1.0.0',                        // API version
    operationId: 'loginUser',                // Custom OpenAPI operation ID

    // Response Configuration
    successObject: User::class,              // Model class for response schema
    successPaginatedObject: Place::class,    // Model class for paginated response
    successMessageOnly: false,               // Response is just a message (no payload)
    successParams: [                         // Custom success response fields
        ['name' => 'token', 'type' => 'string', 'description' => 'Auth token']
    ],

    // Schema References
    successSchema: 'UserResponse',           // Custom success schema name
    errorSchema: 'ErrorResponse',            // Custom error schema name

    // Metadata
    tags: ['auth', 'login'],                 // Additional categorization
    deprecated: 'Use /v2/login instead',     // Deprecation message (null if active)
    rateLimit: ['limit' => 60, 'period' => 'minute'],  // Rate limiting info
    consumes: ['application/json'],          // Content types accepted

    // Headers & Parameters
    headers: ['api_key', 'x-access-token'],  // Config header names to include
    addDefaultHeaders: true,                 // Include default headers from config
    params: [],                              // Inline parameter definitions
    requestExample: ['email' => 'test@example.com'],  // Request body example

    // Reusable Documentation Blocks
    define: ['title' => 'auth_block', 'description' => 'Auth docs'],  // Define a block
    use: ['common_errors', 'auth_headers'],  // Reference defined blocks

    // Error Documentation
    possibleErrors: [                        // List of possible error codes
        400 => 'Bad Request',
        401 => 'Unauthorized',
        422 => 'Validation Error',
        500 => 'Server Error'
    ]
)]
```

#### DocParam Options

```php
#[DocParam(
    name: 'age',                    // Parameter name
    type: 'integer',                // Type: string, integer, number, boolean, array, file
    description: 'User age',        // Description
    example: 25,                    // Example value
    required: true,                 // Is required? (default: true)
    default: null,                  // Default value
    enum: [18, 21, 25, 30],         // Allowed values
    min: 18,                        // Minimum value/length
    max: 100,                       // Maximum value/length
    pattern: '^\d+$',               // Regex pattern
    location: 'body'                // body, query, or path
)]
```

#### DocHeader Options

```php
#[DocHeader(
    name: 'Authorization',          // Header name
    description: 'Bearer token',    // Description
    example: 'Bearer eyJ...',       // Example value
    required: true,                 // Is required? (default: true)
    default: null                   // Default value
)]
```

#### DocResponse Options

```php
#[DocResponse(
    status: 200,                    // HTTP status code
    description: 'Success',         // Response description
    example: ['result' => true],    // Example response body
    isError: false                  // Is this an error response?
)]
```

#### Complete Real-World Example

Here's a production-ready example using all available attribute features:

```php
use EasyDoc\Attributes\DocAPI;
use EasyDoc\Attributes\DocParam;
use EasyDoc\Attributes\DocResponse;

#[DocAPI(
    name: 'Register User',
    group: 'Authentication',
    description: 'Create a new user account and return an authentication token. The user will be immediately logged in and can use the returned token for subsequent API requests.',
    successObject: User::class,
    version: '1.0.0',
    operationId: 'registerUser',
    tags: ['auth', 'registration', 'public'],
    consumes: ['application/json'],
    successParams: [
        ['name' => 'token', 'type' => 'string', 'description' => 'JWT authentication token'],
        ['name' => 'token_type', 'type' => 'string', 'description' => 'Token type (Bearer)']
    ],
    possibleErrors: [
        422 => 'Validation Error - Invalid input data',
        500 => 'Server Error - Failed to create user'
    ],
    rateLimit: ['limit' => 5, 'period' => 'minute'],
    requestExample: [
        'name' => 'John Doe',
        'email' => 'john@example.com',
        'password' => 'secret123',
        'password_confirmation' => 'secret123'
    ]
)]
#[DocParam(
    name: 'name',
    type: 'string',
    description: 'Full name of the user',
    example: 'John Doe',
    required: true,
    min: 2,
    max: 255
)]
#[DocParam(
    name: 'email',
    type: 'string',
    description: 'User email address (must be unique)',
    example: 'john@example.com',
    required: true,
    pattern: '^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$'
)]
#[DocParam(
    name: 'password',
    type: 'string',
    description: 'User password (min 8 characters)',
    example: 'secret123',
    required: true,
    min: 8
)]
#[DocParam(
    name: 'password_confirmation',
    type: 'string',
    description: 'Password confirmation (must match password)',
    example: 'secret123',
    required: true
)]
#[DocResponse(
    status: 201,
    description: 'User created successfully',
    example: [
        'result' => true,
        'message' => 'User registered successfully',
        'payload' => [
            'user' => ['id' => 1, 'name' => 'John Doe', 'email' => 'john@example.com'],
            'token' => 'eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9...',
            'token_type' => 'Bearer',
        ],
    ]
)]
#[DocResponse(
    status: 422,
    description: 'Validation error',
    example: [
        'result' => false,
        'message' => 'The given data was invalid.',
        'errors' => ['email' => ['The email has already been taken.']],
    ],
    isError: true
)]
public function register(Request $request)
{
    // Clean controller - only business logic!
}
```

> **Note:** Both approaches (`document()` function and Attributes) are fully supported. Use whichever fits your coding style!

### 2. View Your Documentation 👁️

Once you have defined your endpoints, view them in the browser.

Make sure to enable the viewer in your `.env`:

```env
EASY_DOC_VISIBLE=true
```

Then visit:

-   **Public Documentation (Redoc)**: `http://your-app.test/api-docs` (Beautiful, client-facing docs)
-   **Dashboard (Swagger UI)**: `http://your-app.test/easy-doc` (Interactive testing dashboard)

---

## 🛠️ API Responses (Trait)

`EasyDoc` provides a convenient trait `ApiResponses` to standardize your API responses.

**Step 1: Use the Trait in your Controller**

```php
use EasyDoc\Traits\ApiResponses;

class AuthController extends Controller
{
    use ApiResponses;

    public function login()
    {
        // ...
        return $this->apiSuccess(['token' => '...']);
    }
}
```

**Available Methods:**

| Method                                  | Usage                                                     | Description                                     |
| :-------------------------------------- | :-------------------------------------------------------- | :---------------------------------------------- |
| `apiSuccess($data, $message, $status)`  | `return $this->apiSuccess($user, 'Created', 201);`        | Returns standardized success structure.         |
| `apiSuccessList($list, $message)`       | `return $this->apiSuccessList($items, 'List retrieved');` | Returns a list of items.                        |
| `apiSuccessPaginated($paginator, $msg)` | `return $this->apiSuccessPaginated($users);`              | Returns paginated data with `meta` and `links`. |
| `apiError($msg, $status, $data)`        | `return $this->apiError('Invalid input', 422);`           | Returns standardized error structure.           |
| `apiNotFound($msg)`                     | `return $this->apiNotFound('User not found');`            | Returns 404 error.                              |
| `apiUnauthorized($msg)`                 | `return $this->apiUnauthorized();`                        | Returns 401 error.                              |
| `apiForbidden($msg)`                    | `return $this->apiForbidden();`                           | Returns 403 error.                              |

**Standard Response Structure:**

```json
{
  "result": true,
  "message": "Operation successful",
  "payload": { ... }
}
```

---

## 🧩 Advanced: Extra API Columns

Sometimes your API returns data that isn't a direct column in your database (e.g., computed attributes, relationships, or tokens). You can document these using the `HasExtraApiColumns` interface on your Model.

```php
use EasyDoc\Contracts\HasExtraApiColumns;

class User extends Authenticatable implements HasExtraApiColumns
{
    /**
     * Define extra API columns for Swagger documentation.
     */
    public function addExtraAPIColumns(): array
    {
        return [
            // Simple type
            'token' => type('string')
                ->description('Authentication token')
                ->nullable(),

            // Relationship (Array of Models)
            'places' => type('array')
                ->description('User places')
                ->of(Place::class), // Links to Place schema

            // Relationship (Single Model)
            'partner' => type('object')
                ->description('User partner')
                ->model(Partner::class)
                ->nullable(),
        ];
    }
}
```

---

## 🚀 Generate Command

Run the artisan command to generate all formats:

```bash
php artisan easy-doc:generate --markdown --openapi3 --sdk
```

This will generate:

-   `public/docs/openapi.json` (OpenAPI 3.0)
-   `public/docs/swagger.json` (Swagger 2.0)
-   `public/docs/postman_collection.json` (Postman)
-   `public/docs/types.ts` (TypeScript Interfaces)

### Performance & Caching ⚡

In production, parsing Attributes and Reflection on every request can be slow. `EasyDoc` provides caching commands to optimize performance.

**Cache Documentation:**
Serializes the parsed documentation to `bootstrap/cache/easy-doc.php`, bypassing the reflection process in subsequent requests.

```bash
php artisan easy-doc:cache
```

**Clear Cache:**
Removes the cached file.

```bash
php artisan easy-doc:clear
```

> **Recommendation:** Add `php artisan easy-doc:cache` to your deployment script.

---

## License

The MIT License (MIT).

---

## 📚 Deep Dive Reference

### Parameter Types & Validation

The `Param` class offers a rich set of validation and typing options.

```php
Param::make('age', Param::TYPE_INT)
    ->min(18)           // Minimum value
    ->max(100)          // Maximum value
    ->optional()        // Mark as optional
    ->description('User age');

Param::make('role', Param::TYPE_STRING)
    ->enum(['admin', 'user', 'guest']) // Enum validation
    ->defaultValue('user');

Param::make('zip_code', Param::TYPE_STRING)
    ->pattern('^\d{5}(?:[-\s]\d{4})?$') // Regex validation
    ->example('90210');
```

**Available Types:**

-   `Param::TYPE_STRING`
-   `Param::TYPE_INT`
-   `Param::TYPE_BOOLEAN`
-   `Param::TYPE_ARRAY`
-   `Param::TYPE_FILE` (See File Uploads below)
-   `Param::TYPE_NUMBER` / `TYPE_FLOAT`

### File Uploads 📂

To document file uploads, use `setConsumes` and `Param::TYPE_FILE`.

```php
document(function() {
    return (new APICall())
        ->setName('Upload Avatar')
        ->setMethod('POST')
        ->setConsumes(['multipart/form-data']) // Important!
        ->setParams([
            Param::make('avatar', Param::TYPE_FILE, 'Profile picture')
                ->required()
        ]);
});
```

### TypeScript SDK Generation 🟦

`EasyDoc` can generate a fully typed TypeScript SDK for your frontend.

1.  **Enable it** in `config/easy-doc.php`:
    ```php
    'output' => [
        'typescript' => [
            'enabled' => true,
            'file' => 'types.ts', // Generates to public/docs/types.ts
        ],
    ],
    ```
2.  **Auto-Discovery**: Your Eloquent models in `app/Models` are automatically converted to TypeScript interfaces (e.g., `interface User { ... }`).

### Advanced Configuration

#### Custom Response Wrapper

If your API wraps every response (e.g., inside `data`), configure it globally to keep your docs accurate.

```php
// config/easy-doc.php
'response_wrapper' => [
    'success' => true,
    'data' => '__DATA__', // The placeholder for your actual response
    'meta' => '__META__',
],
```

#### Multiple Environments

Document your Staging and Production servers so users can switch between them in the UI.

```php
// config/easy-doc.php
'servers' => [
    ['url' => 'http://localhost/api/v1', 'description' => 'Local Dev'],
    ['url' => 'https://staging.api.com/v1', 'description' => 'Staging'],
    ['url' => 'https://api.com/v1', 'description' => 'Production'],
],
```

### Rate Limiting & Deprecation

```php
(new APICall())
    ->name('Legacy Endpoint')
    ->deprecated('Use /new-api instead') // Marks as deprecated
    ->rateLimit(60, 'minute'); // Documents 60 req/min limit
```

---

## 🚀 Developer-Friendly Features (v0.4)

### DocGroup - Controller-Level Defaults

Apply common settings to all endpoints in a controller. No more repeating `group`, `version`, `tags` on every method!

```php
use EasyDoc\Attributes\DocGroup;
use EasyDoc\Attributes\DocAPI;

#[DocGroup(
    group: 'Authentication',
    version: '1.0.0',
    tags: ['auth'],
    consumes: ['application/json'],
    headers: ['x-api-key'],            // All methods get this header
    possibleErrors: [401 => 'Unauthenticated']  // Common errors
)]
class AuthController extends Controller
{
    #[DocAPI(name: 'Login')]  // Inherits group, version, tags from DocGroup
    public function login() { }

    #[DocAPI(name: 'Logout')]  // Also inherits all DocGroup settings
    public function logout() { }
}
```

**DocGroup Properties:**

| Property            | Description                             |
| ------------------- | --------------------------------------- |
| `group`             | Default group for all methods           |
| `version`           | Default API version                     |
| `tags`              | Default tags for all methods            |
| `consumes`          | Default content types                   |
| `headers`           | Config header names for all methods     |
| `addDefaultHeaders` | Include default headers (default: true) |
| `rateLimit`         | Default rate limit                      |
| `possibleErrors`    | Common errors for all methods           |

---

### DocError - Error Response Presets

Reference common error responses from config instead of writing them out every time.

**Step 1: Define presets in config:**

```php
// config/easy-doc.php
'error_presets' => [
    'validation' => [
        'status' => 422,
        'description' => 'Validation Error',
        'example' => ['result' => false, 'message' => 'The given data was invalid.'],
    ],
    'unauthenticated' => [
        'status' => 401,
        'description' => 'Unauthenticated',
        'example' => ['result' => false, 'message' => 'Unauthenticated.'],
    ],
    'not_found' => [
        'status' => 404,
        'description' => 'Not Found',
        'example' => ['result' => false, 'message' => 'Resource not found.'],
    ],
],
```

**Step 2: Use in controllers:**

```php
use EasyDoc\Attributes\DocError;

#[DocAPI(name: 'Update User')]
#[DocError('validation')]      // Uses 422 preset
#[DocError('unauthenticated')] // Uses 401 preset
#[DocError('not_found')]       // Uses 404 preset
public function update(Request $request, User $user) { }
```

**Available Default Presets:**

-   `validation` (422)
-   `unauthenticated` (401)
-   `unauthorized` (403)
-   `not_found` (404)
-   `rate_limit` (429)
-   `server_error` (500)

---

### Param Templates - Reusable Parameter Definitions

Define common parameters once, reuse everywhere.

**Step 1: Define templates in config:**

```php
// config/easy-doc.php
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
    'page' => [
        'type' => 'integer',
        'description' => 'Page number',
        'example' => 1,
        'required' => false,
        'location' => 'query',
    ],
],
```

**Step 2: Use in controllers:**

```php
// Instead of this:
#[DocParam(name: 'email', type: 'string', description: 'Email address', example: 'user@example.com', required: true)]
#[DocParam(name: 'password', type: 'string', description: 'Password', example: 'secret123', required: true, min: 8)]

// Just write this:
#[DocParam(template: 'email')]
#[DocParam(template: 'password')]
public function login(Request $request) { }
```

**Override template values:**

```php
// Use template but override specific values
#[DocParam(template: 'email', description: 'Admin email address')]
```

---

### Comparison: Before vs After

````carousel
### Before: Verbose and Repetitive

```php
#[DocAPI(
    name: 'Login',
    group: 'Authentication',
    version: '1.0.0',
    tags: ['auth'],
    consumes: ['application/json']
)]
#[DocParam(name: 'email', type: 'string', description: 'Email', example: 'user@example.com')]
#[DocParam(name: 'password', type: 'string', description: 'Password', example: 'secret123', min: 8)]
#[DocResponse(status: 422, description: 'Validation Error',
    example: ['result' => false, 'message' => 'Invalid data'], isError: true)]
#[DocResponse(status: 401, description: 'Unauthenticated',
    example: ['result' => false, 'message' => 'Unauthenticated.'], isError: true)]
public function login() { }
```
<!-- slide -->
### After: Clean and DRY

```php
#[DocGroup(group: 'Authentication', version: '1.0.0', tags: ['auth'])]
class AuthController extends Controller
{
    #[DocAPI(name: 'Login')]
    #[DocParam(template: 'email')]
    #[DocParam(template: 'password')]
    #[DocError('validation')]
    #[DocError('unauthenticated')]
    public function login() { }
}
```
````
