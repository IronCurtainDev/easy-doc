# EasyDoc 📚

A lightweight, developer-friendly API documentation generator for Laravel.

**Stop writing YAML manually.** `EasyDoc` auto-generates beautiful Markdown documentation, OpenAPI (Swagger) specs, Postman collections, and even a fully typed TypeScript SDK directly from your Laravel codebase using a fluent, expressive API.

---

## 🚀 Features

- **Fluent API**: Define documentation directly in your Controller logic.
- **Automatic Schema Discovery**: Eloquent models are automatically scanned.
- **Mobile Ready**: Generated **OpenAPI 3.0** & **Swagger 2.0** specs are perfect for generating **iOS (Swift)** and **Android (Kotlin)** clients via generic code generators.
- **Multi-Format Output**: Markdown, OpenAPI 3.0, Swagger 2.0, Postman, TypeScript SDK.
- **Configurable Headers**: Define global authentication headers once in your config.

---

## 🎯 Why Easy-Doc?

### 🏢 For Teams: The "Bus Factor" Solution

If your backend developer leaves, does the next person know how the API works?
With `Easy-Doc`, documentation lives **inside the code**.

- **Knowledge Transfer**: The docs are right next to the logic.
- **Self-Explaining Code**: The Fluent API (`->name('Login')`) makes intent clear.

### 🛡️ Real-World Resilience

Projects get paused. Clients change requirements. Developers changes.

- **Project Restarts**: Paused for 6 months? Since docs are code, they don't "rot". You pick up exactly where you left off.
- **Change Requests**: When a client changes a requirement, you change the code AND the doc in the same file. No desync. No "I forgot to update the wiki".

### 🧠👨‍💻 For Solo Devs: Your "External Brain"

Working alone? `Easy-Doc` acts as your memory.

- **Completeness Check**: By explicitly defining endpoints, you instantly spot missing descriptions or edge cases.
- **Future-Proofing**: Come back to your project 6 months later and know _exactly_ what every endpoint does without re-reading the execution logic.

**It bridges the gap between "Code" and "Explanation".**

---

## 📦 Installation

Install via Composer:

### Stable Version (Recommended)

```bash
composer require ironcurtaindev/easy-doc:^0.1
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

### 2. View Your Documentation 👁️

Once you have defined your endpoints, view them in the browser.

Make sure to enable the viewer in your `.env`:

```env
EASY_DOC_VISIBLE=true
```

Then visit:

- **Public Documentation (Redoc)**: `http://your-app.test/api-docs` (Beautiful, client-facing docs)
- **Dashboard (Swagger UI)**: `http://your-app.test/easy-doc` (Interactive testing dashboard)

---

## 🛠️ API Response Macros

`EasyDoc` provides standardized response macros to ensure consistency across your API.

| Macro                                   | Usage                                                          | Description                                     |
| :-------------------------------------- | :------------------------------------------------------------- | :---------------------------------------------- |
| `apiSuccess($data, $message, $status)`  | `return response()->apiSuccess($user, 'Created', 201);`        | Returns standardized success structure.         |
| `apiSuccessList($list, $message)`       | `return response()->apiSuccessList($items, 'List retrieved');` | Returns a list of items.                        |
| `apiSuccessPaginated($paginator, $msg)` | `return response()->apiSuccessPaginated($users);`              | Returns paginated data with `meta` and `links`. |
| `apiError($msg, $status, $data)`        | `return response()->apiError('Invalid input', 422);`           | Returns standardized error structure.           |
| `apiNotFound($msg)`                     | `return response()->apiNotFound('User not found');`            | Returns 404 error.                              |
| `apiUnauthorized($msg)`                 | `return response()->apiUnauthorized();`                        | Returns 401 error.                              |
| `apiValidationError($errors, $msg)`     | `return response()->apiValidationError($validator->errors());` | Returns 422 with validation errors.             |

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

- `public/docs/openapi.json` (OpenAPI 3.0)
- `public/docs/swagger.json` (Swagger 2.0)
- `public/docs/postman_collection.json` (Postman)
- `public/docs/types.ts` (TypeScript Interfaces)

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

- `Param::TYPE_STRING`
- `Param::TYPE_INT`
- `Param::TYPE_BOOLEAN`
- `Param::TYPE_ARRAY`
- `Param::TYPE_FILE` (See File Uploads below)
- `Param::TYPE_NUMBER` / `TYPE_FLOAT`

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
