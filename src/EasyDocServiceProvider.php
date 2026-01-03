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
        $middleware = config('easy-doc.viewer.middleware', ['web']);

        Route::middleware($middleware)
            ->group(function () use ($routePath) {
                Route::get($routePath, [DocumentationController::class, 'index'])
                    ->name('easy-doc.viewer');
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
            return response()->json([
                'result' => true,
                'message' => $message,
                'payload' => $data,
            ], $statusCode);
        });

        /**
         * Return an error API response.
         */
        Response::macro('apiError', function (string $message = 'An error occurred', int $statusCode = 400, $data = null) {
            $response = [
                'result' => false,
                'message' => $message,
            ];

            if ($data !== null) {
                $response['payload'] = $data;
            }

            return response()->json($response, $statusCode);
        });

        /**
         * Return a paginated API response.
         */
        Response::macro('apiSuccessPaginated', function ($paginator, string $message = '') {
            return response()->json([
                'result' => true,
                'message' => $message,
                'payload' => $paginator->items(),
                'meta' => [
                    'current_page' => $paginator->currentPage(),
                    'from' => $paginator->firstItem(),
                    'last_page' => $paginator->lastPage(),
                    'per_page' => $paginator->perPage(),
                    'to' => $paginator->lastItem(),
                    'total' => $paginator->total(),
                ],
                'links' => [
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
            return response()->json([
                'result' => false,
                'message' => $message,
            ], 404);
        });

        /**
         * Return an unauthorized API response.
         */
        Response::macro('apiUnauthorized', function (string $message = 'Unauthorized') {
            return response()->json([
                'result' => false,
                'message' => $message,
            ], 401);
        });

        /**
         * Return a validation error API response.
         */
        Response::macro('apiValidationError', function ($errors, string $message = 'Validation failed') {
            if ($errors instanceof \Illuminate\Support\MessageBag) {
                $errors = $errors->toArray();
            }

            return response()->json([
                'result' => false,
                'message' => $message,
                'errors' => $errors,
            ], 422);
        });
    }
}
