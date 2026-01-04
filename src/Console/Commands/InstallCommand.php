<?php

namespace EasyDoc\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class InstallCommand extends Command
{
    protected $signature = 'easy-doc:install';

    protected $description = 'Install and configure EasyDoc';

    public function handle()
    {
        $this->info('Installing EasyDoc...');

        // 1. Publish Config
        if (!File::exists(config_path('easy-doc.php'))) {
            $this->call('vendor:publish', [
                '--provider' => "EasyDoc\EasyDocServiceProvider",
                '--tag' => "config"
            ]);
            $this->info('Configuration published.');
        } else {
            $this->comment('Configuration already exists. Skipping publish.');
        }

        // 2. Publish IDE helper for better autocomplete
        if (!File::exists(base_path('_ide_helpers_easy_doc.php'))) {
            $this->call('vendor:publish', [
                '--provider' => "EasyDoc\EasyDocServiceProvider",
                '--tag' => "easy-doc-ide-helper"
            ]);
            $this->info('IDE helper published for better autocomplete support.');
        } else {
            $this->comment('IDE helper already exists. Skipping.');
        }

        // 3. Ask for simple configuration
        $title = $this->ask('What is the name of your API?', 'My API');
        $basePath = $this->ask('What is the base path for your API routes?', '/api/v1');

        // 4. Update config file
        $this->updateConfigFile($title, $basePath);

        $this->info('EasyDoc installed successfully! 🚀');
        $this->comment('Run "php artisan easy-doc:generate --auto" to generate your docs immediately.');
    }

    protected function updateConfigFile($title, $basePath)
    {
        $configPath = config_path('easy-doc.php');
        $content = File::get($configPath);

        // Simple string replacement for demo purposes.
        // In a real app complexity, we might parse AST, but for this config structure, regex is fine.

        $content = preg_replace(
            "/'title' => .*,/",
            "'title' => '$title Documentation',",
            $content
        );

        $content = preg_replace(
            "/'base_path' => .*,/",
            "'base_path' => '$basePath',",
            $content
        );

        File::put($configPath, $content);
    }
}
