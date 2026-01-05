<?php

declare(strict_types=1);

namespace EasyDoc\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class ClearCacheCommand extends Command
{
    protected $signature = 'easy-doc:clear';
    protected $description = 'Clear the API documentation cache';

    public function handle(): int
    {
        $cachePath = base_path('bootstrap/cache/easy-doc.php');

        if (File::exists($cachePath)) {
            File::delete($cachePath);
            $this->info('Documentation cache cleared!');
        } else {
            $this->info('No documentation cache found.');
        }

        return 0;
    }
}
