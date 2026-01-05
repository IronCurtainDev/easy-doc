<?php

/** @noinspection PhpUndefinedClassInspection */
/** @noinspection PhpMultipleClassDeclarationsInspection */

namespace Illuminate\Routing {

    /**
     * @method \Illuminate\Http\JsonResponse apiSuccess($data = null, string $message = '', int $statusCode = 200)
     * @method \Illuminate\Http\JsonResponse apiSuccessList($data = [], string $message = '', int $statusCode = 200)
     * @method \Illuminate\Http\JsonResponse apiError(string $message = 'An error occurred', int $statusCode = 400, $data = null, string $code = null)
     * @method \Illuminate\Http\JsonResponse apiSuccessPaginated($paginator, string $message = '')
     * @method \Illuminate\Http\JsonResponse apiNotFound(string $message = 'Resource not found')
     * @method \Illuminate\Http\JsonResponse apiUnauthorized(string $message = 'Unauthorized')
     * @method \Illuminate\Http\JsonResponse apiValidationError($errors, string $message = 'Validation failed')
     */
    class ResponseFactory {}
}

namespace Illuminate\Support\Facades {
    /**
     * @method static \Illuminate\Http\JsonResponse apiSuccess($data = null, string $message = '', int $statusCode = 200)
     * @method static \Illuminate\Http\JsonResponse apiSuccessList($data = [], string $message = '', int $statusCode = 200)
     * @method static \Illuminate\Http\JsonResponse apiError(string $message = 'An error occurred', int $statusCode = 400, $data = null, string $code = null)
     * @method static \Illuminate\Http\JsonResponse apiSuccessPaginated($paginator, string $message = '')
     * @method static \Illuminate\Http\JsonResponse apiNotFound(string $message = 'Resource not found')
     * @method static \Illuminate\Http\JsonResponse apiUnauthorized(string $message = 'Unauthorized')
     * @method static \Illuminate\Http\JsonResponse apiValidationError($errors, string $message = 'Validation failed')
     */
    class Response {}
}

namespace EasyDoc\Attributes {
    /**
     * Main attribute for documenting API endpoints.
     * Apply to controller methods.
     *
     * @Attribute(Attribute::TARGET_METHOD)
     */
    #[\Attribute(\Attribute::TARGET_METHOD)]
    class DocAPI
    {
        public function __construct(
            public ?string $name = null,
            public ?string $group = null,
            public ?string $description = null,
            public ?string $successObject = null,
            public ?string $successPaginatedObject = null,
            public bool $successMessageOnly = false,
            public ?string $operationId = null,
            public string $version = '0.2.0',
            public array $tags = [],
            public ?string $deprecated = null,
            public ?array $rateLimit = null,
            public array $consumes = [],
            public bool $addDefaultHeaders = true,
            public array $headers = [],
            public array $params = [],
            public array $requestExample = [],
            public array $successParams = [],
            public ?array $define = null,
            public string|array $use = [],
            public array $possibleErrors = [],
            public ?string $successSchema = null,
            public ?string $errorSchema = null,
        ) {}
    }

    /**
     * Attribute for defining request parameters.
     * Repeatable - can add multiple to a method.
     *
     * @Attribute(Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)
     */
    #[\Attribute(\Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
    class DocParam
    {
        public function __construct(
            public string $name,
            public string $type = 'string',
            public ?string $description = null,
            public mixed $example = null,
            public bool $required = true,
            public mixed $default = null,
            public ?array $enum = null,
            public int|float|null $min = null,
            public int|float|null $max = null,
            public ?string $pattern = null,
            public string $location = 'body',
            public ?string $template = null,
        ) {}
    }

    /**
     * Attribute for defining request headers.
     * Repeatable - can add multiple to a method.
     *
     * @Attribute(Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)
     */
    #[\Attribute(\Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
    class DocHeader
    {
        public function __construct(
            public string $name,
            public ?string $description = null,
            public ?string $example = null,
            public bool $required = true,
            public ?string $default = null,
        ) {}
    }

    /**
     * Attribute for defining response examples.
     * Repeatable - can add multiple for different status codes.
     *
     * @Attribute(Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)
     */
    #[\Attribute(\Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
    class DocResponse
    {
        public function __construct(
            public int $status,
            public ?string $description = null,
            public array $example = [],
            public bool $isError = false,
        ) {}
    }

    /**
     * Controller-level attribute for setting default documentation options.
     *
     * @Attribute(Attribute::TARGET_CLASS)
     */
    #[\Attribute(\Attribute::TARGET_CLASS)]
    class DocGroup
    {
        public function __construct(
            public ?string $group = null,
            public string $version = '1.0.0',
            public array $tags = [],
            public array $consumes = ['application/json'],
            public ?string $descriptionPrefix = null,
            public bool $addDefaultHeaders = true,
            public array $headers = [],
            public ?array $rateLimit = null,
            public array $possibleErrors = [],
            public array $security = [],
        ) {}
    }

    /**
     * Shorthand attribute for adding error responses from config presets.
     *
     * @Attribute(Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)
     */
    #[\Attribute(\Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
    class DocError
    {
        public function __construct(
            public string $preset,
            public ?string $description = null,
            public ?array $example = null,
        ) {}
    }
}
