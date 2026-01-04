<?php

namespace EasyDoc\Console\Commands;

use EasyDoc\Contracts\GeneratorInterface;
use EasyDoc\Docs\DocBuilder;
use EasyDoc\Domain\FileGenerators\Markdown\MarkdownGenerator;
use EasyDoc\Domain\FileGenerators\OpenApi\OpenApiGenerator;
use EasyDoc\Domain\FileGenerators\Postman\PostmanGenerator;
use EasyDoc\Domain\FileGenerators\SDK\TypeScriptSDKGenerator;
use EasyDoc\Domain\FileGenerators\Swagger\SwaggerGenerator;
use EasyDoc\Domain\Traits\NamesAndPathLocations;
use EasyDoc\Domain\Vendors\ApiDoc;
use EasyDoc\Services\OpenApiConverter;
use EasyDoc\Services\RouteDiscoveryService;
use Illuminate\Console\Command;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\File;

class GenerateDocsCommand extends Command
{
    use NamesAndPathLocations;

    protected $signature = 'easy-doc:generate
                            {--format=both : Output format: swagger2, openapi3, or both}
                            {--reset : Reset and start fresh}
                            {--auto : Auto-generate documentation for all endpoints}
                            {--no-apidoc : Skip ApiDoc HTML generation}
                            {--no-files-output : Do not show generated files}
                            {--markdown : Generate markdown documentation}
                            {--sdk : Generate TypeScript SDK client}
                            {--snapshot : Save a version snapshot for changelog}
                            {--diff : Show changes since last version snapshot}
                            {--prune= : Keep only last N version snapshots}';

    protected $description = 'Generate API Documentation with configurable headers';

    protected Router $router;
    protected DocBuilder $docBuilder;
    protected string $docsFolder;
    protected array $createdFiles = [];

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
        $this->docBuilder = app('easy-doc.builder');

        if (!File::isDirectory($this->docsFolder)) {
            File::makeDirectory($this->docsFolder, 0755, true);
        }

        putenv('DOCUMENTATION_MODE=true');

        if ($this->option('reset')) {
            $this->docBuilder->reset();
            $this->createdFiles = [];
        }

        $this->info('');
        $this->info('+------------------------------------------------------------+');
        $this->info('|                    Easy-Doc Generator                      |');
        $this->info('+------------------------------------------------------------+');
        $this->info('');

        try {
            // 1. Discovery Phase
            $discovery = new RouteDiscoveryService($this->router, $this->docBuilder);
            $discovery->setOutput($this->output);

            $discovery->discoverModels();
            $discovery->discoverRoutes(config('easy-doc.base_path', '/api/v1'));

            $apiCalls = $this->docBuilder->getApiCalls();
            if ($apiCalls->isEmpty()) {
                $this->warn('No API calls documented');
                return 0;
            }

            // 2. Generation Phase
            $generators = $this->resolveGenerators();
            $converter = new OpenApiConverter(); // Shared converter instance

            foreach ($generators as $generator) {
                $generated = $generator->generate($apiCalls, $this->docsFolder);
                foreach ($generated as $name => $path) {
                    $this->createdFiles[] = [$name, $this->stripBasePath($path)];
                }
            }

            // 3. Post-Processing (ApiDoc, Snapshots, etc)
            $this->handlePostProcessing();

            // 4. Report
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

    protected function resolveGenerators(OpenApiConverter $converter): array
    {
        $generators = [];
        $format = $this->option('format');

        // Swagger v2
        if (in_array($format, ['swagger2', 'both'])) {
            $generators[] = new SwaggerGenerator($converter);
        }

        // OpenAPI v3
        if (in_array($format, ['openapi3', 'both'])) {
            $generators[] = new OpenApiGenerator($converter);
        }

        // Postman (Dependent on Swagger JSON commonly, but our generator handles it)
        $generators[] = new PostmanGenerator($converter);

        // Markdown
        if ($this->option('markdown')) {
            $generators[] = new MarkdownGenerator();
        }

        // SDK
        if ($this->option('sdk')) {
            $generators[] = new TypeScriptSDKGenerator();
        }

        return $generators;
    }

    protected function handlePostProcessing(): void
    {
        // ApiDoc HTML Compilation
        if (!$this->option('no-apidoc')) {
            $this->compileApiDoc();
        }

        // Version Snapshot
        if ($this->option('snapshot')) {
            $this->saveVersionSnapshot();
        }

        // Version Diff
        if ($this->option('diff')) {
            $this->showVersionDiff();
        }

        // Pruning
        if ($pruneCount = $this->option('prune')) {
            $this->pruneOldVersions((int) $pruneCount);
        }

        // Copy Swagger UI
        $this->copySwaggerUI();
    }

    protected function compileApiDoc(): void
    {
        // (Keep existing simplified logic or move to service if needed, keeping it here for now as it orchestrates external process)
        $this->info('');
        $this->info('Compiling ApiDoc HTML documentation...');

        if (!ApiDoc::isInstalled()) {
            $this->warn('  [SKIP] ApiDoc.js is not installed');
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
            // $this->warn('  [WARN] Swagger UI HTML template not found');
            return;
        }

        $destinationPath = $this->docsFolder . DIRECTORY_SEPARATOR . 'index.html';
        File::copy($sourcePath, $destinationPath);
        $this->createdFiles[] = ['Swagger UI (HTML)', $this->stripBasePath($destinationPath)];
    }

    protected function stripBasePath(string $path): string
    {
        return str_replace(base_path(), '', $path);
    }
}
