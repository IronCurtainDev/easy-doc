# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [0.4.0] - 2026-01-05

### Added

- **DocGroup Attribute** - Controller-level defaults for all endpoints:
  - Set `group`, `version`, `tags`, `consumes` once at the class level
  - Common `headers` and `possibleErrors` inherited by all methods
  - `rateLimit` defaults for all endpoints
- **DocError Attribute** - Shorthand for common error responses:
  - Reference error presets by name: `#[DocError('validation')]`
  - Built-in presets: `validation`, `unauthenticated`, `unauthorized`, `not_found`, `rate_limit`, `server_error`
  - Define custom presets in `config/easy-doc.php`
- **Param Templates** - Reusable parameter definitions:
  - Define once in config: `'param_templates' => ['email' => [...]]`
  - Use in controllers: `#[DocParam(template: 'email')]`
  - Override specific values while using template
- New config options: `param_templates` and `error_presets`

### Changed

- `DocParam` now accepts optional `template` property
- Updated IDE helpers for new attributes

---

## [0.3.0] - 2026-01-05

### Added

- **PHP 8 Attributes Support** - New attribute-based API documentation as an alternative to the `document()` function approach:
  - `#[DocAPI]` - Main attribute for endpoint documentation with full feature parity:
    - Basic: `name`, `group`, `description`, `version`, `operationId`
    - Response: `successObject`, `successPaginatedObject`, `successMessageOnly`, `successParams`
    - Schema: `successSchema`, `errorSchema`
    - Meta: `tags`, `deprecated`, `rateLimit`, `consumes`
    - Headers: `headers`, `addDefaultHeaders`
    - Parameters: `params`, `requestExample`
    - Reusable blocks: `define`, `use`
    - Errors: `possibleErrors`
  - `#[DocParam]` - Repeatable attribute for request parameters with type, example, validation constraints
  - `#[DocHeader]` - Repeatable attribute for request headers
  - `#[DocResponse]` - Repeatable attribute for success and error response examples
- New `AttributeReader` service for parsing PHP attributes via Reflection API
- Updated `RouteDiscoveryService` to detect and use attributes before invoking controller methods
- IDE helper stubs for all new attribute classes

### Changed

- Updated README with comprehensive documentation for PHP Attributes approach
- Updated composer.json description and keywords

### Notes

Both documentation approaches are fully supported:

1. **`document()` function** - Inline documentation inside controller methods
2. **PHP 8 Attributes** - Declarative documentation outside method bodies

---

## [0.2.0] - 2026-01-04

### Added

- Response examples (success and error) in `APICall` class
- Request body examples with auto-generation from parameters
- Postman environment file generation
- API response macros (`apiSuccess`, `apiError`, `apiSuccessPaginated`, etc.)
- Extra API columns support via `HasExtraApiColumns` interface
- TypeScript SDK generation

### Changed

- Improved Swagger/OpenAPI generators with better schema handling
- Enhanced Postman collection generation with response examples

---

## [0.1.0] - 2026-01-03

### Added

- Initial release
- Fluent API for defining endpoint documentation
- Automatic Eloquent model schema discovery
- Multi-format output: Swagger 2.0, OpenAPI 3.0, Postman, Markdown
- Configurable authentication headers
- Documentation viewer routes (`/api-docs` and `/easy-doc`)
