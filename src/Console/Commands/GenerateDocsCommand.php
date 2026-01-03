<?php

namespace EasyDoc\Console\Commands;

use EasyDoc\Docs\APICall;
use EasyDoc\Docs\DocBuilder;
use EasyDoc\Docs\Param;
use EasyDoc\Domain\FileGenerators\OpenApi\OpenApiSchema;
use EasyDoc\Domain\FileGenerators\Postman\PostmanCollectionBuilder;
use EasyDoc\Domain\FileGenerators\Swagger\SwaggerV2;
use EasyDoc\Domain\Traits\NamesAndPathLocations;
use EasyDoc\Domain\Vendors\ApiDoc;
use EasyDoc\Exceptions\DocumentationModeEnabledException;
use Illuminate\Console\Command;
use Illuminate\Routing\Router;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class GenerateDocsCommand extends Command
{
    use NamesAndPathLocations;

    protected $signature = 'easy-doc:generate
                            {--format=both : Output format: swagger2, openapi3, or both}
                            {--reset : Reset and start fresh}
                            {--auto : Auto-generate documentation for all endpoints}
                            {--no-apidoc : Skip ApiDoc HTML generation}
                            {--no-files-output : Do not show generated files}';

    protected $description = 'Generate API Documentation with configurable headers';

    protected Router $router;
    protected DocBuilder $docBuilder;
    protected string $docsFolder;
    protected array $createdFiles = [];
    protected string $format = 'both';
    protected string $basePath = '/api/v1';

    public function __construct(Router $router)
    {
        parent::__construct();
        $this->router = $router;
    }

    public function handle(): int
    {
        if (app()->environment('production')) {
            $this->error('This command cannot be run in production environment.');
            return 1;
        }

        $this->docsFolder = config('easy-doc.output.path', public_path('docs'));
        $this->basePath = config('easy-doc.base_path', '/api/v1');

        if (!File::isDirectory($this->docsFolder)) {
            File::makeDirectory($this->docsFolder, 0755, true);
        }

        putenv('DOCUMENTATION_MODE=true');

        $this->docBuilder = app('easy-doc.builder');

        if ($this->option('reset')) {
            $this->docBuilder->reset();
            $this->createdFiles = [];
        }

        $this->format = $this->option('format');
        if (!in_array($this->format, ['swagger2', 'openapi3', 'both'])) {
            $this->error("Invalid format. Use: swagger2, openapi3, or both");
            return 1;
        }

        $this->info('');
        $this->info('+------------------------------------------------------------+');
        $this->info('|                    Easy-Doc Generator                      |');
        $this->info('+------------------------------------------------------------+');
        $this->info('');
        $this->showConfiguredHeaders();
        $this->info('Starting API documentation generation...');
        $this->info("Output format: {$this->format}");
        $this->info('');

        try {
            $this->defineDefaultHeaders();
            $this->hitRoutesAndLoadDocs();
            $this->createDocSourceFiles();

            if (in_array($this->format, ['swagger2', 'both'])) {
                $this->createSwaggerJson();
            }

            if (in_array($this->format, ['openapi3', 'both'])) {
                $this->createOpenApiJson();
            }

            $this->createPostmanCollection();
            $this->createPostmanEnvironment();
            $this->copySwaggerUI();

            if (!$this->option('no-apidoc')) {
                $this->compileApiDoc();
            }

            if (!$this->option('no-files-output')) {
                $this->info('');
                $this->table(['Generated File', 'Path'], $this->createdFiles);
            }

            $this->info('');
            $this->info('[OK] API documentation generated successfully!');
            $this->info('');
        } catch (\Exception $ex) {
            $this->error('Error generating documentation: ' . $ex->getMessage());
            $this->error($ex->getTraceAsString());
            return 1;
        }

        putenv('DOCUMENTATION_MODE=false');
        return 0;
    }

    protected function showConfiguredHeaders(): void
    {
        $authHeaders = config('easy-doc.auth_headers', []);

        if (empty($authHeaders)) {
            $this->warn('No auth headers configured in config/easy-doc.php');
            $this->info('');
            return;
        }

        $this->info('Configured Authentication Headers:');
        foreach ($authHeaders as $header) {
            $required = ($header['required'] ?? true) ? 'required' : 'optional';
            $this->line("  - {$header['name']} ({$required})");
        }
        $this->info('');
    }

    protected function defineDefaultHeaders(): void
    {
        try {
            document(function () {
                $apiCall = new APICall();
                $apiCall->setDefine('default_headers');

                $headers = [];
                $headers[] = (new Param('Accept', Param::TYPE_STRING, 'Set to application/json'))
                    ->setDefaultValue('application/json');

                $apiCall->setHeaders($headers);
                return $apiCall;
            });
        } catch (DocumentationModeEnabledException $ex) {
            // Expected
        }
    }

    protected function hitRoutesAndLoadDocs(): void
    {
        $routes = $this->router->getRoutes();
        $apiRoutes = new Collection();
        $searchPrefix = ltrim($this->basePath, '/');

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

        $bar = $this->output->createProgressBar($apiRoutes->count());
        $bar->start();

        foreach ($apiRoutes as $route) {
            try {
                $this->hitRoute($route);
            } catch (DocumentationModeEnabledException $ex) {
                // Expected - documentation was registered
            } catch (\Exception $ex) {
                // Skip routes that fail
            }
            $bar->advance();
        }

        $bar->finish();
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

        // Spy on FormRequest injection
        $rules = [];
        try {
            $methodReflection = $reflection->getMethod($method);
            foreach ($methodReflection->getParameters() as $param) {
                $type = $param->getType();
                if ($type && !$type->isBuiltin()) {
                    $paramClass = $type->getName();
                    if (class_exists($paramClass) && is_subclass_of($paramClass, \Illuminate\Foundation\Http\FormRequest::class)) {
                        // Instantiate the FormRequest to get rules
                        // We use app() to resolve dependencies if any
                        try {
                            $formRequest = app($paramClass);
                            if (method_exists($formRequest, 'rules')) {
                                $rules = $formRequest->rules();
                            }
                        } catch (\Throwable $e) {
                            // Ignore if we can't instantiate or get rules
                        }
                    }
                }
            }
        } catch (\Throwable $e) {
            // Ignore reflection errors
        }

        $this->docBuilder->setInterceptor(
            $route->methods()[0] ?? 'GET',
            $route->uri(),
            $controllerAction,
            $rules // Pass captured rules
        );

        $initialCount = $this->docBuilder->getApiCalls()->count();

        $request = \Illuminate\Http\Request::create(
            $route->uri(),
            $route->methods()[0] ?? 'GET'
        );

        try {
            $controllerInstance = app($controller);
            $controllerInstance->$method($request);
        } finally {
            // Auto-generation Check
            $finalCount = $this->docBuilder->getApiCalls()->count();
            if ($finalCount === $initialCount && $this->option('auto')) {
                $this->docBuilder->autoRegister();
                $this->info("  Auto-generated: {$route->uri()}");
            }

            $this->docBuilder->clearInterceptor();
        }
    }

    protected function createDocSourceFiles(): void
    {
        $docsFolder = self::getApiDocsAutoGenDir(true);
        $items = $this->docBuilder->getApiCalls();

        if ($items->isEmpty()) {
            $this->warn('No API calls documented');
            return;
        }

        self::deleteFilesInDirectory($docsFolder, 'coffee');

        foreach ($items as $item) {
            $outputFile = Str::snake($item->getGroup() . '.coffee');
            $outputPath = $docsFolder . DIRECTORY_SEPARATOR . $outputFile;

            $lines = [];
            $lines[] = "# AUTO-GENERATED. DO NOT EDIT THIS FILE.";
            $lines[] = $item->getApiDoc();
            $lines[] = '';
            file_put_contents($outputPath, implode("\r\n", $lines), FILE_APPEND);
        }

        $this->createdFiles[] = ['ApiDoc Source Files', $this->stripBasePath($docsFolder)];
    }

    protected function compileApiDoc(): void
    {
        $this->info('');
        $this->info('Compiling ApiDoc HTML documentation...');

        if (!ApiDoc::isInstalled()) {
            $this->warn('  [SKIP] ApiDoc.js is not installed');
            $this->line(ApiDoc::getInstallInstructions());
            return;
        }

        try {
            $process = ApiDoc::compile();

            if ($process->isSuccessful()) {
                $this->info('  [OK] ApiDoc HTML compiled successfully');
                $this->createdFiles[] = ['ApiDoc HTML', $this->stripBasePath(self::getApiDocsOutputDir())];
            } else {
                $this->warn('  [WARN] ApiDoc compilation had warnings: ' . $process->getErrorOutput());
            }
        } catch (\Exception $e) {
            $this->warn('  [WARN] ApiDoc compilation failed: ' . $e->getMessage());
        }
    }

    protected function copySwaggerUI(): void
    {
        $sourcePath = dirname(__DIR__, 2) . '/resources/assets/docs/swagger.html';

        if (!File::exists($sourcePath)) {
            $sourcePath = dirname(__DIR__, 3) . '/resources/assets/docs/swagger.html';
        }

        if (!File::exists($sourcePath)) {
            $this->warn('  [WARN] Swagger UI HTML template not found');
            return;
        }

        $destinationPath = $this->docsFolder . DIRECTORY_SEPARATOR . 'index.html';
        File::copy($sourcePath, $destinationPath);
        $this->createdFiles[] = ['Swagger UI (HTML)', $this->stripBasePath($destinationPath)];
    }

    protected function createSwaggerJson(): void
    {
        $items = $this->docBuilder->getApiCalls();

        if ($items->isEmpty()) {
            $this->warn('No API calls to document');
            return;
        }

        $swaggerConfig = new SwaggerV2();
        $swaggerConfig->setBasePath($this->basePath);
        $swaggerConfig->setServerUrl(config('app.url'));

        // Add defined schemas
        $schemas = \EasyDoc\Docs\SchemaBuilder::all();
        foreach ($schemas as $name => $schema) {
            $swaggerConfig->addDefinition($name, $schema);
        }

        foreach ($items as $item) {
            $route = $item->getRoute();
            if (empty($route) || !empty($item->getDefine())) {
                continue;
            }

            $method = strtolower($item->getMethod());
            $parameters = $this->buildParameters($item, $method);
            $pathSuffix = str_replace(ltrim($this->basePath, '/'), '', $route);

            // Build description with rate limit info
            $description = $item->getDescription() ?? '';
            if ($item->getRateLimit()) {
                $rateLimit = $item->getRateLimit();
                $description .= "\n\n**Rate Limit:** {$rateLimit['limit']} requests per {$rateLimit['period']}";
            }
            if ($item->isDeprecated()) {
                $description = "**DEPRECATED:** {$item->getDeprecationMessage()}\n\n" . $description;
            }

            $pathData = [
                'tags' => $item->getTags(),
                'summary' => $item->getName(),
                'description' => $description,
                'operationId' => $item->getOperationId(),
                'consumes' => ['application/json'],
                'produces' => ['application/json'],
                'parameters' => $parameters,
                'security' => $swaggerConfig->getSecuritySchemes(),
                'responses' => $this->buildResponses($item),
            ];

            // Add deprecation flag
            if ($item->isDeprecated()) {
                $pathData['deprecated'] = true;
            }

            // Add rate limit extension
            if ($item->getRateLimit()) {
                $pathData['x-rateLimit'] = $item->getRateLimit();
            }

            $swaggerConfig->addPathData($pathSuffix, $method, $pathData);
        }

        $outputPath = $this->docsFolder . DIRECTORY_SEPARATOR . 'swagger.yml';
        $swaggerConfig->writeOutputFileYaml($outputPath);
        $this->createdFiles[] = ['Swagger v2 (YAML)', $this->stripBasePath($outputPath)];

        $outputPath = $this->docsFolder . DIRECTORY_SEPARATOR . 'swagger.json';
        $swaggerConfig->writeOutputFileJson($outputPath);
        $this->createdFiles[] = ['Swagger v2 (JSON)', $this->stripBasePath($outputPath)];
    }

    protected function createOpenApiJson(): void
    {
        $items = $this->docBuilder->getApiCalls();

        if ($items->isEmpty()) {
            $this->warn('No API calls to document');
            return;
        }

        $openApiConfig = new OpenApiSchema();
        $openApiConfig->setBasePath($this->basePath);
        $openApiConfig->setServerUrl(config('app.url') . $this->basePath, 'Current Server');

        // Add defined schemas
        $schemas = \EasyDoc\Docs\SchemaBuilder::all();
        foreach ($schemas as $name => $schema) {
            $openApiConfig->addSchema($name, $schema);
        }

        foreach ($items as $item) {
            $route = $item->getRoute();
            if (empty($route) || !empty($item->getDefine())) {
                continue;
            }

            $method = strtolower($item->getMethod());
            $parameters = $this->buildParameters($item, $method);
            $pathSuffix = str_replace(ltrim($this->basePath, '/'), '', $route);

            // Build description with rate limit info
            $description = $item->getDescription() ?? '';
            if ($item->getRateLimit()) {
                $rateLimit = $item->getRateLimit();
                $description .= "\n\n**Rate Limit:** {$rateLimit['limit']} requests per {$rateLimit['period']}";
            }
            if ($item->isDeprecated()) {
                $description = "**DEPRECATED:** {$item->getDeprecationMessage()}\n\n" . $description;
            }

            $pathData = [
                'tags' => $item->getTags(),
                'summary' => $item->getName(),
                'description' => $description,
                'operationId' => $item->getOperationId(),
                'parameters' => $parameters,
                'security' => $openApiConfig->getSecuritySchemes(),
                'responses' => $this->buildResponses($item),
            ];

            // Add deprecation flag
            if ($item->isDeprecated()) {
                $pathData['deprecated'] = true;
            }

            $openApiConfig->addPathData($pathSuffix, $method, $pathData);
        }

        $outputPath = $this->docsFolder . DIRECTORY_SEPARATOR . 'openapi.yml';
        $openApiConfig->writeOutputFileYaml($outputPath);
        $this->createdFiles[] = ['OpenAPI 3.0 (YAML)', $this->stripBasePath($outputPath)];

        $outputPath = $this->docsFolder . DIRECTORY_SEPARATOR . 'openapi.json';
        $openApiConfig->writeOutputFileJson($outputPath);
        $this->createdFiles[] = ['OpenAPI 3.0 (JSON)', $this->stripBasePath($outputPath)];
    }

    protected function createPostmanCollection(): void
    {
        $swaggerPath = $this->docsFolder . DIRECTORY_SEPARATOR . 'swagger.json';

        if (!File::exists($swaggerPath)) {
            $this->createSwaggerJson();
        }

        if (!File::exists($swaggerPath)) {
            $this->warn('Could not create Postman collection - no swagger.json found');
            return;
        }

        $swaggerSchema = json_decode(File::get($swaggerPath), true);

        $postmanBuilder = new PostmanCollectionBuilder();
        $postmanBuilder->buildFromSwagger($swaggerSchema);

        $outputPath = $this->docsFolder . DIRECTORY_SEPARATOR . 'postman_collection.json';
        $postmanBuilder->writeOutputFileJson($outputPath);
        $this->createdFiles[] = ['Postman Collection', $this->stripBasePath($outputPath)];
    }

    protected function buildParameters(APICall $item, string $method): array
    {
        $parameters = [];

        // Add path parameters
        foreach ($item->getPathParams() as $param) {
            $paramData = $this->buildParamData($param, Param::LOCATION_PATH);
            $parameters[] = $paramData;
        }

        // Add query parameters
        foreach ($item->getQueryParams() as $param) {
            $paramData = $this->buildParamData($param, Param::LOCATION_QUERY);
            $parameters[] = $paramData;
        }

        // Add headers and body params
        $allParams = array_merge($item->getHeaders(), $item->getParams());

        foreach ($allParams as $param) {
            $location = $param->getLocation();
            if ($location === null) {
                $location = $method === 'get' ? Param::LOCATION_QUERY : Param::LOCATION_FORM;
            }

            $paramData = $this->buildParamData($param, $location);
            $parameters[] = $paramData;
        }

        return $parameters;
    }

    /**
     * Build parameter data array with enum, min, max, pattern support.
     */
    protected function buildParamData(Param $param, string $location): array
    {
        $paramData = [
            'name' => $param->getName(),
            'in' => $location,
            'required' => $param->getRequired(),
            'description' => $param->getDescription(),
            'type' => strtolower($param->getDataType()),
        ];

        // Add enum values if set
        if ($param->getEnum() !== null) {
            $paramData['enum'] = $param->getEnum();
        }

        // Add validation constraints
        if ($param->getMin() !== null) {
            $paramData['minimum'] = $param->getMin();
        }

        if ($param->getMax() !== null) {
            $paramData['maximum'] = $param->getMax();
        }

        if ($param->getPattern() !== null) {
            $paramData['pattern'] = $param->getPattern();
        }

        if ($param->getDefaultValue() !== null) {
            $paramData['default'] = $param->getDefaultValue();
        }

        if ($param->getExample() !== null) {
            $paramData['example'] = $param->getExample();
        }

        return $paramData;
    }

    protected function stripBasePath(string $path): string
    {
        return str_replace(base_path(), '', $path);
    }

    protected function buildResponses(APICall $item): array
    {
        $responses = [];

        $successExamples = $item->getSuccessExamples();
        if (!empty($successExamples)) {
            foreach ($successExamples as $code => $data) {
                $responses[(string)$code] = [
                    'description' => $data['description'],
                    'examples' => [
                        'application/json' => $data['example'],
                    ],
                ];
            }
        } else {
            $responses['200'] = ['description' => 'Successful response'];
        }

        $errorExamples = $item->getErrorExamples();
        if (!empty($errorExamples)) {
            foreach ($errorExamples as $code => $data) {
                $responses[(string)$code] = [
                    'description' => $data['description'],
                    'examples' => [
                        'application/json' => $data['example'],
                    ],
                ];
            }
        } else {
            $responses['401'] = ['description' => 'Unauthorized'];
            $responses['422'] = ['description' => 'Validation error'];
        }

        return $responses;
    }

    protected function createPostmanEnvironment(): void
    {
        $authHeaders = config('easy-doc.auth_headers', []);
        $appUrl = config('app.url', 'http://localhost:8000');

        $environment = [
            'id' => 'easy-doc-env-' . time(),
            'name' => config('app.name', 'API') . ' Environment',
            'values' => [
                [
                    'key' => 'base_url',
                    'value' => $appUrl . config('easy-doc.base_path', '/api/v1'),
                    'type' => 'default',
                    'enabled' => true,
                ],
            ],
            '_postman_variable_scope' => 'environment',
        ];

        foreach ($authHeaders as $header) {
            $environment['values'][] = [
                'key' => str_replace(['-', '_'], '_', $header['name']),
                'value' => $header['example'] ?? '',
                'type' => 'secret',
                'enabled' => true,
            ];
        }

        $outputPath = $this->docsFolder . DIRECTORY_SEPARATOR . 'postman_environment.json';
        File::put($outputPath, json_encode($environment, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        $this->createdFiles[] = ['Postman Environment', $this->stripBasePath($outputPath)];
    }
}
