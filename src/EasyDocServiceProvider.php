<?php

declare(strict_types=1);

namespace EasyDoc;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Route;
use EasyDoc\Docs\DocBuilder;
use EasyDoc\Console\Commands\GenerateDocsCommand;
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
                Console\Commands\CacheDocsCommand::class,
                Console\Commands\ClearCacheCommand::class,
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

            // Publish IDE helper file for better IDE autocomplete support
            $this->publishes([
                __DIR__ . '/_ide_helpers.php' => base_path('_ide_helpers_easy_doc.php'),
            ], 'easy-doc-ide-helper');
        }

        // Register documentation viewer route
        $this->registerRoutes();
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

                Route::get('docs/scalar', [DocumentationController::class, 'scalar'])
                    ->name('easy-doc.scalar');
            });
    }
}
