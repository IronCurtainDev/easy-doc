<?php

/** @noinspection PhpUndefinedClassInspection */
/** @noinspection PhpMultipleClassDeclarationsInspection */

namespace Illuminate\Routing {

    /**
     * @method \Illuminate\Http\JsonResponse apiSuccess($data = null, string $message = '', int $statusCode = 200)
     * @method \Illuminate\Http\JsonResponse apiError(string $message = 'An error occurred', int $statusCode = 400, $data = null)
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
     * @method static \Illuminate\Http\JsonResponse apiError(string $message = 'An error occurred', int $statusCode = 400, $data = null)
     * @method static \Illuminate\Http\JsonResponse apiSuccessPaginated($paginator, string $message = '')
     * @method static \Illuminate\Http\JsonResponse apiNotFound(string $message = 'Resource not found')
     * @method static \Illuminate\Http\JsonResponse apiUnauthorized(string $message = 'Unauthorized')
     * @method static \Illuminate\Http\JsonResponse apiValidationError($errors, string $message = 'Validation failed')
     */
    class Response {}
}
