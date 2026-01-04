# EasyDoc 📚

A lightweight, developer-friendly API documentation generator for Laravel.

**Stop writing YAML manually.** `EasyDoc` auto-generates beautiful Markdown documentation, OpenAPI (Swagger) specs, Postman collections, and even a fully typed TypeScript SDK directly from your Laravel codebase using a fluent, expressive API.

---

## 🚀 Features

- **Fluent API**: Define documentation where your code lives (in Controllers or Routes).
- **Automatic Schema Discovery**: Your Eloquent models are automatically scanned and converted to schemas. No manual definition needed!
- **Multi-Format Output**: Markdown, OpenAPI 3.0, Swagger 2.0, Postman, TypeScript SDK.
- **Configurable Headers**: Define global authentication headers once in your config.

---

## 📦 Installation

Install via Composer:

```bash
composer require iron-curtain/easy-doc
```

Publish the configuration (Critical for Header setup):

```bash
php artisan vendor:publish --provider="EasyDoc\EasyDocServiceProvider"
```

---

## ⚙️ Configuration (Headers & Models)

### Authentication Headers

Define your API keys or Tokens in `config/easy-doc.php`. This sets them globally for `authenticated()` endpoints.

```php
// config/easy-doc.php

'auth_headers' => [
    [
        'name' => 'x-api-key',
        'type' => 'api_key', // or 'bearer'
        'description' => 'Your API Key',
        'required' => true,
        'example' => 'abcdef123456', // Used in Postman/Curl examples
    ],
],
```

### Model Auto-Discovery

By default, `EasyDoc` scans your `app/Models` directory.
You just need to ensure your models are standard Eloquent models.

```php
'auto_discover_models' => true,
'model_path' => app_path('Models'),
```

---

## 📖 Usage Guide

Since schemas are auto-discovered, you focus purely on **Documenting Endpoints**.

**Scenario**: A **User** has one **Partner** and many **Places**.
(Assumes `User`, `Partner`, and `Place` models exist in `App\Models`)

### Document Your Endpoints 📝

Use the `document()` helper in your Controllers.

#### Authentication & Registration

```php
// AuthController.php

public function register(Request $request) {
    document(function($doc) {
        return $doc->name('Register User')
            ->group('Auth')
            ->body(['name', 'email', 'password', 'password_confirmation'])
            ->response(201, 'User created', ['token' => 'abc...']);
    });
}

public function login(Request $request) {
    document(function($doc) {
        return $doc->name('Login')
            ->group('Auth')
            ->body(['email', 'password'])
            ->possibleErrors([
                'INVALID_CREDENTIALS' => 'Wrong email or password',
            ])
            ->response(200, 'Login successful', ['token' => 'abc...', 'user' => schema('User')]);
    });
}
```

#### Protected Endpoints

Use `->authenticated()` to automatically apply the headers defined in your config.

```php
// PlaceController.php

public function store(Request $request) {
    document(function($doc) {
        return $doc->name('Add New Place')
            ->group('Places')
            ->authenticated() // <--- Uses 'x-api-key' from config
            ->body(['address', 'latitude', 'longitude'])
            ->response(201, 'Place Added', schema('Place'));
    });
}
```

### Generate Documentation ⚡

Run the artisan command to generate all formats:

```bash
php artisan easy-doc:generate --markdown --openapi3 --sdk
```

---

## 💡 Advanced Usage

### Configurable Responses

Customize generic response wrappers in `config/easy-doc.php` to match your API's style (e.g., using "success" instead of "result").

### Deprecation

Mark fields or endpoints as deprecated.

```php
$doc->name('Old Endpoint')->deprecated('Use /new-endpoint instead');
```

## License

The MIT License (MIT).
