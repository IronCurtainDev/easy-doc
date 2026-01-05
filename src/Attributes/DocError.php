<?php

declare(strict_types=1);

namespace EasyDoc\Attributes;

use Attribute;

/**
 * Shorthand attribute for adding error responses from config presets.
 * Apply to controller methods to add common error responses.
 *
 * @example
 * #[DocError('validation')]
 * #[DocError('unauthenticated')]
 * #[DocError('not_found')]
 * public function show(Request $request) { ... }
 */
#[Attribute(Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
class DocError
{
    public function __construct(
        /**
         * Name of the error preset from config('easy-doc.error_presets').
         * Available presets: validation, unauthenticated, unauthorized, not_found, rate_limit, server_error
         */
        public string $preset,

        /**
         * Override the preset's description.
         */
        public ?string $description = null,

        /**
         * Override the preset's example.
         */
        public ?array $example = null,
    ) {}
}
