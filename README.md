# EasyDoc 📚

A lightweight, developer-friendly API documentation generator for Laravel.

**Stop writing YAML manually.** `EasyDoc` auto-generates beautiful Markdown documentation, OpenAPI (Swagger) specs, Postman collections, and even a fully typed TypeScript SDK directly from your Laravel codebase using a fluent, expressive API.

---

## 🚀 Features

- **Fluent API**: Define documentation directly in your Controller logic.
- **Automatic Schema Discovery**: Eloquent models are automatically scanned.
- **Multi-Format Output**: Markdown, OpenAPI 3.0, Swagger 2.0, Postman, TypeScript SDK.
- **Flexible Headers**: Define headers globally in config OR locally in your document call.

---

## 📦 Installation

Install via Composer:

```bash
composer require iron-curtain/easy-doc
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
```

#### Protected Endpoints (Headers)

You can define authentication headers directly in the documentation block.

```php
// PlaceController.php

public function store(Request $request) {
    document(function($doc) {
        return $doc->name('Add New Place')
            ->group('Places')
            // Define Header Locally
            ->header('x-api-key', 'abcdef123', 'Your public API key')
            ->body(['address', 'latitude', 'longitude'])
            ->response(201, 'Place Added', schema('Place'));
    });
}
```

**Alternative:** If you prefer, you _can_ configure global headers in `config/easy-doc.php` and references them, but it is **not mandatory**.

```php
// Option: Use pre-configured header if set in config
// ->authenticated()
```

### Generate Documentation ⚡

Run the artisan command to generate all formats:

```bash
php artisan easy-doc:generate --markdown --openapi3 --sdk
```

---

## 💡 Advanced Usage

### Configurable Responses

Customize generic response wrappers in `config/easy-doc.php`.

### Deprecation

Mark fields or endpoints as deprecated.

```php
$doc->name('Old Endpoint')->deprecated('Use /new-endpoint instead');
```

## License

The MIT License (MIT).
