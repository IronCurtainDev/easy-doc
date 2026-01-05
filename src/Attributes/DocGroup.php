<?php

declare(strict_types=1);

namespace EasyDoc\Attributes;

use Attribute;

/**
 * Controller-level attribute for setting default documentation options.
 * Apply to a controller class to set defaults for all methods.
 *
 * @example
 * #[DocGroup(
 *     group: 'Authentication',
 *     version: '1.0.0',
 *     tags: ['auth'],
 *     consumes: ['application/json']
 * )]
 * class AuthController extends Controller { ... }
 */
#[Attribute(Attribute::TARGET_CLASS)]
class DocGroup
{
    public function __construct(
        /**
         * Default group/category for all endpoints in this controller.
         */
        public ?string $group = null,

        /**
         * Default API version for all endpoints.
         */
        public string $version = '1.0.0',

        /**
         * Default tags for all endpoints.
         */
        public array $tags = [],

        /**
         * Default content types for all endpoints.
         */
        public array $consumes = ['application/json'],

        /**
         * Default description prefix for all endpoints.
         */
        public ?string $descriptionPrefix = null,

        /**
         * Whether to add default headers from config to all endpoints.
         */
        public bool $addDefaultHeaders = true,

        /**
         * Default headers for all endpoints (config header names).
         */
        public array $headers = [],

        /**
         * Default rate limit for all endpoints.
         */
        public ?array $rateLimit = null,

        /**
         * Common possible errors for all endpoints.
         */
        public array $possibleErrors = [],

        /**
         * Security requirements for all endpoints.
         */
        public array $security = [],
    ) {}
}
