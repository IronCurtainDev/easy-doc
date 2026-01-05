<?php

declare(strict_types=1);

namespace EasyDoc\Console\Commands;

use Illuminate\Console\Command;
use EasyDoc\Services\RouteDiscoveryService;
use EasyDoc\Docs\DocBuilder;
use Illuminate\Support\Facades\File;

class CacheDocsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'easy-doc:cache';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a cache file for faster API documentation loading';

    /**
     * Execute the console command.
     */
    public function handle(RouteDiscoveryService $discovery, DocBuilder $builder): int
    {
        $this->call('easy-doc:generate', [
            '--no-files-output' => true,
            '--no-apidoc' => true
        ]);

        $apiCalls = $builder->getApiCalls()->toArray();

        $cachePath = base_path('bootstrap/cache/easy-doc.php');
        $directory = dirname($cachePath);

        if (!File::exists($directory)) {
            File::makeDirectory($directory, 0755, true);
        }

        $content = "<?php\n\nreturn " . var_export($apiCalls, true) . ";\n";

        File::put($cachePath, $content);

        $this->info("Documentation cached successfully at: {$cachePath}");
        return 0;
    }
}
