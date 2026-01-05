<?php

declare(strict_types=1);

namespace EasyDoc\Domain\Changelog;

use Illuminate\Support\Facades\File;

/**
 * Manage API versions and generate changelogs.
 */
class ChangelogManager
{
    protected string $storageDir;
    protected array $currentSchema = [];

    public function __construct()
    {
        $this->storageDir = storage_path('easy-doc/versions');
    }

    /**
     * Set the storage directory for version snapshots.
     */
    public function setStorageDir(string $path): static
    {
        $this->storageDir = $path;
        return $this;
    }

    /**
     * Set current schema for comparison.
     */
    public function setCurrentSchema(array $schema): static
    {
        $this->currentSchema = $schema;
        return $this;
    }

    /**
     * Save a version snapshot.
     */
    public function saveSnapshot(string $version = null): string
    {
        $this->ensureStorageDir();

        $version = $version ?? date('Y-m-d_H-i-s');
        $filename = "v_{$version}.json";
        $path = $this->storageDir . '/' . $filename;

        $snapshot = [
            'version' => $version,
            'timestamp' => now()->toIso8601String(),
            'schema' => $this->currentSchema,
        ];

        File::put($path, json_encode($snapshot, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        return $path;
    }

    /**
     * Get all saved versions.
     *
     * @return array Array of version info ['version' => string, 'timestamp' => string, 'path' => string]
     */
    public function getVersions(): array
    {
        $this->ensureStorageDir();

        $files = File::glob($this->storageDir . '/v_*.json');
        $versions = [];

        foreach ($files as $file) {
            $content = json_decode(File::get($file), true);
            $versions[] = [
                'version' => $content['version'] ?? basename($file, '.json'),
                'timestamp' => $content['timestamp'] ?? null,
                'path' => $file,
            ];
        }

        // Sort by timestamp descending (newest first)
        usort($versions, fn($a, $b) => strcmp($b['timestamp'] ?? '', $a['timestamp'] ?? ''));

        return $versions;
    }

    /**
     * Get the latest saved version.
     */
    public function getLatestVersion(): ?array
    {
        $versions = $this->getVersions();
        return $versions[0] ?? null;
    }

    /**
     * Load a version snapshot.
     */
    public function loadVersion(string $version): ?array
    {
        $path = $this->storageDir . "/v_{$version}.json";

        if (!File::exists($path)) {
            return null;
        }

        return json_decode(File::get($path), true);
    }

    /**
     * Compare current schema with a saved version.
     *
     * @return array Diff with 'added', 'removed', 'modified' keys
     */
    public function compareWith(string $version): array
    {
        $oldSnapshot = $this->loadVersion($version);
        if (!$oldSnapshot) {
            return ['error' => "Version {$version} not found"];
        }

        $oldSchema = $oldSnapshot['schema'] ?? [];
        $newSchema = $this->currentSchema;

        return $this->diffSchemas($oldSchema, $newSchema);
    }

    /**
     * Compare with the latest saved version.
     */
    public function compareWithLatest(): array
    {
        $latest = $this->getLatestVersion();
        if (!$latest) {
            return ['error' => 'No previous versions found'];
        }

        return $this->compareWith($latest['version']);
    }

    /**
     * Diff two schemas.
     */
    protected function diffSchemas(array $old, array $new): array
    {
        $diff = [
            'added' => [
                'endpoints' => [],
                'schemas' => [],
                'fields' => [],
            ],
            'removed' => [
                'endpoints' => [],
                'schemas' => [],
                'fields' => [],
            ],
            'modified' => [
                'endpoints' => [],
                'fields' => [],
            ],
        ];

        // Compare paths/endpoints
        $oldPaths = array_keys($old['paths'] ?? []);
        $newPaths = array_keys($new['paths'] ?? []);

        $diff['added']['endpoints'] = array_diff($newPaths, $oldPaths);
        $diff['removed']['endpoints'] = array_diff($oldPaths, $newPaths);

        // Compare schemas
        $oldSchemas = array_keys($old['definitions'] ?? []);
        $newSchemas = array_keys($new['definitions'] ?? []);

        $diff['added']['schemas'] = array_diff($newSchemas, $oldSchemas);
        $diff['removed']['schemas'] = array_diff($oldSchemas, $newSchemas);

        // Compare fields within schemas
        $commonSchemas = array_intersect($oldSchemas, $newSchemas);
        foreach ($commonSchemas as $schemaName) {
            $oldProps = array_keys($old['definitions'][$schemaName]['properties'] ?? []);
            $newProps = array_keys($new['definitions'][$schemaName]['properties'] ?? []);

            $addedProps = array_diff($newProps, $oldProps);
            $removedProps = array_diff($oldProps, $newProps);

            if (!empty($addedProps)) {
                $diff['added']['fields'][$schemaName] = array_values($addedProps);
            }
            if (!empty($removedProps)) {
                $diff['removed']['fields'][$schemaName] = array_values($removedProps);
            }
        }

        return $diff;
    }

    /**
     * Generate a markdown changelog.
     */
    public function generateChangelog(array $diff): string
    {
        $md = "# API Changelog\n\n";
        $md .= "Generated: " . now()->toDateTimeString() . "\n\n";

        $hasChanges = false;

        // Added
        if (!empty($diff['added']['endpoints']) || !empty($diff['added']['schemas']) || !empty($diff['added']['fields'])) {
            $hasChanges = true;
            $md .= "## ➕ Added\n\n";

            if (!empty($diff['added']['endpoints'])) {
                $md .= "### New Endpoints\n";
                foreach ($diff['added']['endpoints'] as $endpoint) {
                    $md .= "- `{$endpoint}`\n";
                }
                $md .= "\n";
            }

            if (!empty($diff['added']['schemas'])) {
                $md .= "### New Schemas\n";
                foreach ($diff['added']['schemas'] as $schema) {
                    $md .= "- `{$schema}`\n";
                }
                $md .= "\n";
            }

            if (!empty($diff['added']['fields'])) {
                $md .= "### New Fields\n";
                foreach ($diff['added']['fields'] as $schema => $fields) {
                    $md .= "**{$schema}:**\n";
                    foreach ($fields as $field) {
                        $md .= "- `{$field}`\n";
                    }
                }
                $md .= "\n";
            }
        }

        // Removed
        if (!empty($diff['removed']['endpoints']) || !empty($diff['removed']['schemas']) || !empty($diff['removed']['fields'])) {
            $hasChanges = true;
            $md .= "## ➖ Removed\n\n";

            if (!empty($diff['removed']['endpoints'])) {
                $md .= "### Removed Endpoints\n";
                foreach ($diff['removed']['endpoints'] as $endpoint) {
                    $md .= "- ~~`{$endpoint}`~~\n";
                }
                $md .= "\n";
            }

            if (!empty($diff['removed']['schemas'])) {
                $md .= "### Removed Schemas\n";
                foreach ($diff['removed']['schemas'] as $schema) {
                    $md .= "- ~~`{$schema}`~~\n";
                }
                $md .= "\n";
            }

            if (!empty($diff['removed']['fields'])) {
                $md .= "### Removed Fields\n";
                foreach ($diff['removed']['fields'] as $schema => $fields) {
                    $md .= "**{$schema}:**\n";
                    foreach ($fields as $field) {
                        $md .= "- ~~`{$field}`~~\n";
                    }
                }
                $md .= "\n";
            }
        }

        if (!$hasChanges) {
            $md .= "> No changes detected.\n";
        }

        return $md;
    }

    /**
     * Prune old versions, keeping only the last N versions.
     */
    public function prune(int $keep = 5): int
    {
        $versions = $this->getVersions();
        $toDelete = array_slice($versions, $keep);

        $deleted = 0;
        foreach ($toDelete as $version) {
            if (File::delete($version['path'])) {
                $deleted++;
            }
        }

        return $deleted;
    }

    /**
     * Delete all version snapshots.
     */
    public function clear(): int
    {
        $versions = $this->getVersions();
        $deleted = 0;

        foreach ($versions as $version) {
            if (File::delete($version['path'])) {
                $deleted++;
            }
        }

        return $deleted;
    }

    /**
     * Ensure storage directory exists.
     */
    protected function ensureStorageDir(): void
    {
        if (!File::isDirectory($this->storageDir)) {
            File::makeDirectory($this->storageDir, 0755, true);
        }
    }
}
