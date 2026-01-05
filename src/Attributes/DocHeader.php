<?php

declare(strict_types=1);

namespace EasyDoc\Attributes;

use Attribute;

/**
 * Attribute for defining individual request headers.
 * This attribute is repeatable - you can add multiple to a single method.
 *
 * @example
 * #[DocHeader(name: 'Authorization', description: 'Bearer token', example: 'Bearer eyJ...')]
 * #[DocHeader(name: 'X-API-Key', description: 'API Key for authentication')]
 * public function protectedEndpoint(Request $request) { ... }
 */
#[Attribute(Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
class DocHeader
{
    public function __construct(
        /**
         * Header name (e.g., 'Authorization', 'X-API-Key').
         */
        public string $name,

        /**
         * Header description.
         */
        public ?string $description = null,

        /**
         * Example value for documentation.
         */
        public ?string $example = null,

        /**
         * Whether the header is required.
         */
        public bool $required = true,

        /**
         * Default value if not provided.
         */
        public ?string $default = null,
    ) {}
}
