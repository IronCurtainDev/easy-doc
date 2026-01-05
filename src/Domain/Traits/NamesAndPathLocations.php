<?php

declare(strict_types=1);

namespace EasyDoc\Domain\Traits;

use Illuminate\Support\Facades\File;

/**
 * Trait for managing documentation file paths and directories.
 */
trait NamesAndPathLocations
{
    /**
     * Get base docs directory.
     */
    protected static function getDocsDir(bool $createIfNotExists = false): string
    {
        $dirPath = resource_path('docs');

        if ($createIfNotExists && !File::isDirectory($dirPath)) {
            File::makeDirectory($dirPath, 0755, true);
        }

        return $dirPath;
    }

    /**
     * Get public docs output directory.
     */
    public static function getDocsOutputDir(bool $createIfNotExists = false): string
    {
        $dirPath = config('easy-doc.output.path', public_path('docs'));

        if ($createIfNotExists && !File::isDirectory($dirPath)) {
            File::makeDirectory($dirPath, 0755, true);
        }

        return $dirPath;
    }

    /**
     * Get API docs output directory (for ApiDoc HTML).
     */
    protected static function getApiDocsOutputDir(bool $createIfNotExists = false): string
    {
        $dirPath = self::getDocsOutputDir() . DIRECTORY_SEPARATOR . 'api';

        if ($createIfNotExists && !File::isDirectory($dirPath)) {
            File::makeDirectory($dirPath, 0755, true);
        }

        return $dirPath;
    }

    /**
     * Get ApiDoc base directory.
     */
    protected static function getApiDocsDir(bool $createIfNotExists = false): string
    {
        $dirPath = self::getDocsDir() . DIRECTORY_SEPARATOR . 'apidoc';

        if ($createIfNotExists && !File::isDirectory($dirPath)) {
            File::makeDirectory($dirPath, 0755, true);
        }

        return $dirPath;
    }

    /**
     * Get auto-generated ApiDoc files directory.
     */
    protected static function getApiDocsAutoGenDir(bool $createIfNotExists = false): string
    {
        $dirPath = self::getApiDocsDir() . DIRECTORY_SEPARATOR . 'auto_generated';

        if ($createIfNotExists && !File::isDirectory($dirPath)) {
            File::makeDirectory($dirPath, 0755, true);
        }

        return $dirPath;
    }

    /**
     * Get manual ApiDoc files directory.
     */
    protected static function getApiDocsManualDir(bool $createIfNotExists = false): string
    {
        $dirPath = self::getApiDocsDir() . DIRECTORY_SEPARATOR . 'manual';

        if ($createIfNotExists && !File::isDirectory($dirPath)) {
            File::makeDirectory($dirPath, 0755, true);
        }

        return $dirPath;
    }

    /**
     * Delete old files in directory.
     */
    public static function deleteFilesInDirectory(string $dirPath, string $fileExtension): void
    {
        array_map('unlink', glob("$dirPath/*.$fileExtension"));
    }
}
