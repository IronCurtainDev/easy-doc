<?php

declare(strict_types=1);

namespace EasyDoc\Traits;

use Illuminate\Http\JsonResponse;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

trait ApiResponses
{
    /**
     * Return a successful API response.
     */
    protected function apiSuccess(mixed $data = null, string $message = '', int $statusCode = 200): JsonResponse
    {
        $keys = config('easy-doc.response.keys', [
            'result' => 'result',
            'message' => 'message',
            'data' => 'payload'
        ]);

        return response()->json([
            $keys['result'] => true,
            $keys['message'] => $message,
            $keys['data'] => $data,
        ], $statusCode);
    }

    /**
     * Return a successful list API response.
     */
    protected function apiSuccessList(mixed $data = [], string $message = '', int $statusCode = 200): JsonResponse
    {
        $keys = config('easy-doc.response.keys', [
            'result' => 'result',
            'message' => 'message',
            'meta' => 'meta',
            'links' => 'links',
            'data' => 'payload'
        ]);

        // Handling Laravel Paginated Resources
        if (
            $data instanceof LengthAwarePaginator ||
            $data instanceof AnonymousResourceCollection
        ) {

            $response = $data->toArray(request());

            return response()->json([
                $keys['result'] => true,
                $keys['message'] => $message,
                $keys['data'] => $response['data'],
                $keys['meta'] => $response['meta'] ?? [],
                $keys['links'] => $response['links'] ?? [],
            ], $statusCode);
        }

        return response()->json([
            $keys['result'] => true,
            $keys['message'] => $message,
            $keys['data'] => $data,
        ], $statusCode);
    }

    /**
     * Return an error API response.
     */
    protected function apiError(string $message = 'An error occurred', int $statusCode = 400, mixed $errors = null): JsonResponse
    {
        $keys = config('easy-doc.response.keys', [
            'result' => 'result',
            'message' => 'message',
            'errors' => 'errors'
        ]);

        return response()->json([
            $keys['result'] => false,
            $keys['message'] => $message,
            $keys['errors'] => $errors,
        ], $statusCode);
    }
}
