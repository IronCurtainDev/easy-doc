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

### � Real-World Resilience

Projects get paused. Clients change requirements. Developers changes.

- **Project Restarts**: Paused for 6 months? Since docs are code, they don't "rot". You pick up exactly where you left off.
- **Change Requests**: When a client changes a requirement, you change the code AND the doc in the same file. No desync. No "I forgot to update the wiki".

### �👨‍💻 For Solo Devs: Your "External Brain"

Working alone? `Easy-Doc` acts as your memory.

- **Completeness Check**: By explicitly defining endpoints, you instantly spot missing descriptions or edge cases.
- **Future-Proofing**: Come back to your project 6 months later and know _exactly_ what every endpoint does without re-reading the execution logic.

**It bridges the gap between "Code" and "Explanation".**

---

## 📦 Installation

Install via Composer:

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
