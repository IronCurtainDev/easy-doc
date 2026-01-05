<?php

namespace EasyDoc\Attributes;

use Attribute;

/**
 * Attribute for defining individual request parameters.
 * This attribute is repeatable - you can add multiple to a single method.
 *
 * @example
 * // Full definition:
 * #[DocParam(name: 'email', type: 'string', description: 'User email address', example: 'john@example.com')]
 *
 * // Using config template (from config/easy-doc.php param_templates):
 * #[DocParam(template: 'email')]
 * #[DocParam(template: 'password')]
 *
 * public function login(Request $request) { ... }
 */
#[Attribute(Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
class DocParam
{
    public function __construct(
        /**
         * Parameter name. Required unless using a template.
         */
        public ?string $name = null,

        /**
         * Data type: 'string', 'integer', 'number', 'boolean', 'array', 'file'.
         */
        public string $type = 'string',

        /**
         * Parameter description.
         */
        public ?string $description = null,

        /**
         * Example value for documentation.
         */
        public mixed $example = null,

        /**
         * Whether the parameter is required.
         */
        public bool $required = true,

        /**
         * Default value if not provided.
         */
        public mixed $default = null,

        /**
         * Allowed enum values.
         */
        public ?array $enum = null,

        /**
         * Minimum value (for numbers) or length (for strings).
         */
        public int|float|null $min = null,

        /**
         * Maximum value (for numbers) or length (for strings).
         */
        public int|float|null $max = null,

        /**
         * Regex pattern for validation.
         */
        public ?string $pattern = null,

        /**
         * Parameter location: 'body', 'query', 'path'.
         */
        public string $location = 'body',

        /**
         * Template name from config('easy-doc.param_templates').
         * When set, loads defaults from config and overrides with any provided values.
         */
        public ?string $template = null,
    ) {}
}
