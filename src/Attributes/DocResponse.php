<?php

declare(strict_types=1);

namespace EasyDoc\Attributes;

use Attribute;

/**
 * Attribute for defining response examples.
 * This attribute is repeatable - you can add multiple for different status codes.
 *
 * @example
 * #[DocResponse(
 *     status: 200,
 *     description: 'Login successful',
 *     example: ['result' => true, 'message' => 'Login successful', 'payload' => [...]]
 * )]
 * #[DocResponse(
 *     status: 422,
 *     description: 'Invalid credentials',
 *     example: ['result' => false, 'message' => 'Validation failed'],
 *     isError: true
 * )]
 * public function login(Request $request) { ... }
 */
#[Attribute(Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
class DocResponse
{
    public function __construct(
        /**
         * HTTP status code (e.g., 200, 201, 400, 422).
         */
        public int $status,

        /**
         * Response description.
         */
        public ?string $description = null,

        /**
         * Example response data.
         */
        public array $example = [],

        /**
         * Whether this is an error response.
         */
        public bool $isError = false,
    ) {}
}
