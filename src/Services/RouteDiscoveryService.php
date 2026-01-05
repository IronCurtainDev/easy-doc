<?php

declare(strict_types=1);

namespace EasyDoc\Services;

use EasyDoc\Docs\DocBuilder;
use EasyDoc\Exceptions\DocumentationModeEnabledException;
use EasyDoc\Services\AttributeReader;
use Illuminate\Routing\Router;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Finder\Finder;

class RouteDiscoveryService
{
    protected Router $router;
    protected DocBuilder $docBuilder;
    protected ?OutputInterface $output = null;

    public function __construct(Router $router, DocBuilder $docBuilder)
    {
        $this->router = $router;
        $this->docBuilder = $docBuilder;
    }

    public function setOutput(OutputInterface $output): void
    {
        $this->output = $output;
    }

    protected function info(string $message): void
    {
        if ($this->output) {
            $this->output->writeln("<info>{$message}</info>");
        }
    }

    protected function warn(string $message): void
    {
        if ($this->output) {
            $this->output->writeln("<comment>{$message}</comment>");
        }
    }

    /**
     * Discover and register models to SchemaBuilder.
     */
    public function discoverModels(): void
    {
        if (!config('easy-doc.auto_discover_models', true)) {
            return;
        }

        $modelPaths = config('easy-doc.model_path', app_path('Models'));

        // Normalize to array
        if (is_string($modelPaths)) {
            $modelPaths = [$modelPaths];
        }

        // Filter valid directories
        $validPaths = array_filter($modelPaths, function ($path) {
            return File::isDirectory($path);
        });

        if (empty($validPaths)) {
            return;
        }

        if (!class_exists(Finder::class)) {
            $this->warn('Symfony Directory Finder component not installed. Model discovery disabled.');
            return;
        }

        $files = Finder::create()
            ->in($validPaths)
            ->files()
            ->name('*.php');

        foreach ($files as $file) {
            $className = $this->getClassFromFile($file);

            if (
                $className &&
                class_exists($className) &&
                is_subclass_of($className, 'Illuminate\Database\Eloquent\Model') &&
                ! (new \ReflectionClass($className))->isAbstract()
            ) {
                try {
                    \EasyDoc\Docs\SchemaBuilder::fromModel($className);
                } catch (\Throwable $e) {
                    if ($this->output && $this->output->isVerbose()) {
                        $this->warn("Failed to register model {$className}: " . $e->getMessage());
                    }
                }
            }
        }
    }

    /**
     * Parse class name from file.
     */
    protected function getClassFromFile(\Symfony\Component\Finder\SplFileInfo $file): ?string
    {
        $contents = $file->getContents();

        if (!preg_match('/namespace\s+(.+?);/', $contents, $matches)) {
            return null;
        }

        $namespace = $matches[1];
        $class = $file->getBasename('.php');

        return $namespace . '\\' . $class;
    }

    /**
     * Discover routes and simulate requests to build documentation.
     */
    public function discoverRoutes(string $basePath): void
    {
        $routes = $this->router->getRoutes();
        $apiRoutes = new Collection();
        $searchPrefix = ltrim($basePath, '/');

        foreach ($routes as $route) {
            if (str_starts_with($route->uri(), $searchPrefix)) {
                $apiRoutes->push($route);
            }
        }

        if ($apiRoutes->isEmpty() && $searchPrefix !== 'api') {
            foreach ($routes as $route) {
                if (str_starts_with($route->uri(), 'api')) {
                    $apiRoutes->push($route);
                }
            }
        }

        $this->info("Found {$apiRoutes->count()} API routes to document");

        $bar = $this->output ? new \Symfony\Component\Console\Helper\ProgressBar($this->output, $apiRoutes->count()) : null;
        if ($bar) $bar->start();

        foreach ($apiRoutes as $route) {
            try {
                $this->hitRoute($route);
            } catch (DocumentationModeEnabledException $ex) {
                // Expected
            } catch (\Exception $ex) {
                // Skip failing routes
            }
            if ($bar) $bar->advance();
        }

        if ($bar) $bar->finish();
        $this->info('');
    }

    protected function hitRoute($route): void
    {
        $action = $route->getAction();

        if (!isset($action['controller'])) {
            return;
        }

        $controllerAction = $action['controller'];

        if (is_string($controllerAction) && str_contains($controllerAction, '@')) {
            [$controller, $method] = explode('@', $controllerAction);
        } elseif (is_array($controllerAction) && count($controllerAction) === 2) {
            [$controller, $method] = $controllerAction;
        } else {
            return;
        }

        if (!class_exists($controller)) {
            return;
        }

        $reflection = new \ReflectionClass($controller);
        if (!$reflection->hasMethod($method)) {
            return;
        }

        // =====================================================
        // PHP 8 Attributes Approach (preferred)
        // Check for DocAPI attribute before invoking the method
        // =====================================================
        $attributeReader = new AttributeReader();
        $apiCall = $attributeReader->readFromMethod($controller, $method);

        if ($apiCall !== null) {
            // Set route and method from the actual route
            $apiCall->setRoute($route->uri());
            $apiCall->setMethod($route->methods()[0] ?? 'GET');

            // Set group from controller name if not specified
            if (empty($apiCall->getGroup())) {
                $group = str_replace('Controller', '', $reflection->getShortName());
                $apiCall->setGroup($group);
            }

            // Register and skip method invocation
            $this->docBuilder->register($apiCall);
            return;
        }

        // =====================================================
        // Legacy Approach: document() function inside method
        // Only runs if no DocAPI attribute was found
        // =====================================================

        // Spy on FormRequest injection
        $rules = $this->resolveFormRequestRules($reflection, $method);

        $this->docBuilder->setInterceptor(
            $route->methods()[0] ?? 'GET',
            $route->uri(),
            $controllerAction,
            $rules
        );

        $initialCount = $this->docBuilder->getApiCalls()->count();

        $request = \Illuminate\Http\Request::create(
            $route->uri(),
            $route->methods()[0] ?? 'GET'
        );

        $methodDependencies = $this->resolveMethodDependencies($reflection, $method, $request);

        try {
            $controllerInstance = app($controller);
            $controllerInstance->$method(...$methodDependencies);
        } finally {
            // Auto-generation Check
            $finalCount = $this->docBuilder->getApiCalls()->count();
            if ($finalCount === $initialCount && config('easy-doc.auto_generate', false)) {
                $this->docBuilder->autoRegister();
                $this->info("  Auto-generated: {$route->uri()}");
            }

            $this->docBuilder->clearInterceptor();
        }
    }

    protected function resolveFormRequestRules(\ReflectionClass $controllerRef, string $method): array
    {
        $rules = [];
        try {
            $methodReflection = $controllerRef->getMethod($method);
            foreach ($methodReflection->getParameters() as $param) {
                $type = $param->getType();
                if ($type instanceof \ReflectionNamedType && !$type->isBuiltin()) {
                    $paramClass = $type->getName();
                    if (class_exists($paramClass) && is_subclass_of($paramClass, \Illuminate\Foundation\Http\FormRequest::class)) {
                        try {
                            $formRequest = app($paramClass);
                            if (method_exists($formRequest, 'rules')) {
                                $rules = $formRequest->rules();
                            }
                        } catch (\Throwable $e) {
                            // Ignore
                        }
                    }
                }
            }
        } catch (\Throwable $e) {
            // Ignore
        }
        return $rules;
    }
    protected function resolveMethodDependencies(\ReflectionClass $controllerRef, string $method, \Illuminate\Http\Request $request): array
    {
        $dependencies = [];
        $methodRef = $controllerRef->getMethod($method);

        foreach ($methodRef->getParameters() as $param) {
            $type = $param->getType();

            // 1. Handle Built-in Types (scalars)
            if (!$type || ($type instanceof \ReflectionNamedType && $type->isBuiltin())) {
                if ($param->isDefaultValueAvailable()) {
                    $dependencies[] = $param->getDefaultValue();
                } else {
                    // Provide safe defaults for non-nullable scalars
                    $typeName = ($type instanceof \ReflectionNamedType) ? $type->getName() : 'string';
                    $dependencies[] = match ($typeName) {
                        'int' => 1,
                        'float' => 1.0,
                        'bool' => true,
                        'array' => [],
                        default => 'test',
                    };
                }
                continue;
            }

            // 2. Handle Classes
            // Only support named types for injection
            if (!($type instanceof \ReflectionNamedType)) {
                $dependencies[] = null;
                continue;
            }

            $typeName = $type->getName();

            // Inject the Request object if type-hinted
            if (is_a($typeName, \Illuminate\Http\Request::class, true)) {
                $dependencies[] = $request;
                continue;
            }

            // Try to resolve from container (for Models, Services, etc.)
            try {
                $dependencies[] = app($typeName);
            } catch (\Throwable $e) {
                // If container resolution fails, try null if allowed
                if ($param->allowsNull()) {
                    $dependencies[] = null;
                } else {
                    // Last resort: try to instantiate directly
                    try {
                        $dependencies[] = new $typeName();
                    } catch (\Throwable $e) {
                        $dependencies[] = null; // Will likely fail type check, but best effort
                    }
                }
            }
        }

        return $dependencies;
    }
}
