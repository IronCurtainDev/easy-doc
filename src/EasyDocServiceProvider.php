<?php

namespace EasyDoc;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Route;
use EasyDoc\Docs\DocBuilder;
use EasyDoc\Console\Commands\GenerateDocsCommand;
use Illuminate\Support\Facades\Response;
use EasyDoc\Http\Controllers\DocumentationController;

class EasyDocServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Merge config
        $this->mergeConfigFrom(__DIR__ . '/../config/easy-doc.php', 'easy-doc');

        // Register DocBuilder as singleton
        if (!$this->app->environment('production')) {
            $this->app->singleton('easy-doc.builder', DocBuilder::class);
        }
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Register commands
        if ($this->app->runningInConsole()) {
            $this->commands([
                GenerateDocsCommand::class,
                Console\Commands\InstallCommand::class,
            ]);

            // Publish configuration
            $this->publishes([
                __DIR__ . '/../config/easy-doc.php' => config_path('easy-doc.php'),
            ], 'easy-doc-config');

            // Publish assets (Swagger UI and apidoc.json)
            $this->publishes([
                __DIR__ . '/../resources/assets/docs/swagger.html' => public_path('docs/swagger.html'),
                __DIR__ . '/../resources/assets/apidoc.json' => base_path('apidoc.json'),
            ], 'easy-doc-assets');
        }

        // Register documentation viewer route
        $this->registerRoutes();

        // Register response macros
        $this->registerResponseMacros();
    }

    /**
     * Register the documentation viewer routes.
     */
    protected function registerRoutes(): void
    {
        // Only register if viewer is enabled
        if (!config('easy-doc.viewer.enabled', false)) {
            return;
        }

        $routePath = config('easy-doc.viewer.route', 'easy-doc');
        $publicPath = config('easy-doc.viewer.public_route', 'docs/public'); // Default public route
        $middleware = config('easy-doc.viewer.middleware', ['web']);

        Route::middleware($middleware)
            ->group(function () use ($routePath, $publicPath) {
                Route::get($routePath, [DocumentationController::class, 'index'])
                    ->name('easy-doc.viewer');

                Route::get($publicPath, [DocumentationController::class, 'redoc'])
                    ->name('easy-doc.public');
            });
    }

    /**
     * Register response macros for consistent API responses.
     */
    protected function registerResponseMacros(): void
    {
        /**
         * Return a successful API response.
         */
        Response::macro('apiSuccess', function ($data = null, string $message = '', int $statusCode = 200) {
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
        });

        /**
         * Return a successful list API response.
         */
        Response::macro('apiSuccessList', function ($data = [], string $message = '', int $statusCode = 200) {
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
        });

        /**
         * Return an error API response.
         */
        Response::macro('apiError', function (string $message = 'An error occurred', int $statusCode = 400, $data = null, string $code = null) {
            $keys = config('easy-doc.response.keys', [
                'result' => 'result',
                'message' => 'message',
                'data' => 'payload',
                'code' => 'code'
            ]);

            $response = [
                $keys['result'] => false,
                $keys['message'] => $message,
            ];

            if ($data !== null) {
                $response[$keys['data']] = $data;
            }

            if ($code !== null && isset($keys['code'])) {
                $response[$keys['code']] = $code;
            }

            return response()->json($response, $statusCode);
        });

        /**
         * Return a paginated API response.
         */
        Response::macro('apiSuccessPaginated', function ($paginator, string $message = '') {
            $keys = config('easy-doc.response.keys', [
                'result' => 'result',
                'message' => 'message',
                'data' => 'payload',
                'meta' => 'meta',
                'links' => 'links'
            ]);

            return response()->json([
                $keys['result'] => true,
                $keys['message'] => $message,
                $keys['data'] => $paginator->items(),
                $keys['meta'] => [
                    'current_page' => $paginator->currentPage(),
                    'from' => $paginator->firstItem(),
                    'last_page' => $paginator->lastPage(),
                    'per_page' => $paginator->perPage(),
                    'to' => $paginator->lastItem(),
                    'total' => $paginator->total(),
                ],
                $keys['links'] => [
                    'first' => $paginator->url(1),
                    'last' => $paginator->url($paginator->lastPage()),
                    'prev' => $paginator->previousPageUrl(),
                    'next' => $paginator->nextPageUrl(),
                ],
            ]);
        });

        /**
         * Return a not found API response.
         */
        Response::macro('apiNotFound', function (string $message = 'Resource not found') {
            $keys = config('easy-doc.response.keys', ['result' => 'result', 'message' => 'message']);

            return response()->json([
                $keys['result'] => false,
                $keys['message'] => $message,
            ], 404);
        });

        /**
         * Return an unauthorized API response.
         */
        Response::macro('apiUnauthorized', function (string $message = 'Unauthorized') {
            $keys = config('easy-doc.response.keys', ['result' => 'result', 'message' => 'message']);

            return response()->json([
                $keys['result'] => false,
                $keys['message'] => $message,
            ], 401);
        });

        /**
         * Return a validation error API response.
         */
        Response::macro('apiValidationError', function ($errors, string $message = 'Validation failed') {
            $keys = config('easy-doc.response.keys', [
                'result' => 'result',
                'message' => 'message',
                'errors' => 'errors'
            ]);

            if ($errors instanceof \Illuminate\Support\MessageBag) {
                $errors = $errors->toArray();
            }

            return response()->json([
                $keys['result'] => false,
                $keys['message'] => $message,
                $keys['errors'] => $errors,
            ], 422);
        });
    }
}
