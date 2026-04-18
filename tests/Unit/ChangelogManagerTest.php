<?php

namespace EasyDoc\Tests\Unit;

use EasyDoc\Domain\Changelog\ChangelogManager;
use EasyDoc\Tests\TestCase;
use Illuminate\Support\Facades\File;

class ChangelogManagerTest extends TestCase
{
    protected string $storageDir;
    protected ChangelogManager $manager;

    protected function setUp(): void
    {
        parent::setUp();

        $this->storageDir = sys_get_temp_dir() . '/easy-doc-changelog-tests-' . uniqid();
        $this->manager = (new ChangelogManager())->setStorageDir($this->storageDir);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->storageDir);
        parent::tearDown();
    }

    public function test_save_and_load_snapshot_and_get_latest_version()
    {
        $schema = ['paths' => ['/users' => []], 'definitions' => ['User' => ['properties' => []]]];

        $snapshotPath = $this->manager
            ->setCurrentSchema($schema)
            ->saveSnapshot('1.0.0');

        $this->assertFileExists($snapshotPath);

        $loaded = $this->manager->loadVersion('1.0.0');
        $this->assertSame('1.0.0', $loaded['version']);
        $this->assertSame($schema, $loaded['schema']);

        $latest = $this->manager->getLatestVersion();
        $this->assertSame('1.0.0', $latest['version']);
        $this->assertSame($snapshotPath, $latest['path']);
    }

    public function test_compare_with_detects_added_removed_endpoints_schemas_and_fields()
    {
        $oldSchema = [
            'paths' => ['/users' => [], '/legacy' => []],
            'definitions' => [
                'User' => ['properties' => ['id' => [], 'name' => []]],
                'Legacy' => ['properties' => ['code' => []]],
            ],
        ];

        File::ensureDirectoryExists($this->storageDir);
        File::put($this->storageDir . '/v_1.0.0.json', json_encode([
            'version' => '1.0.0',
            'timestamp' => '2026-01-01T00:00:00+00:00',
            'schema' => $oldSchema,
        ]));

        $newSchema = [
            'paths' => ['/users' => [], '/posts' => []],
            'definitions' => [
                'User' => ['properties' => ['id' => [], 'email' => []]],
                'Post' => ['properties' => ['id' => []]],
            ],
        ];

        $diff = $this->manager
            ->setCurrentSchema($newSchema)
            ->compareWith('1.0.0');

        $this->assertEqualsCanonicalizing(['/posts'], $diff['added']['endpoints']);
        $this->assertEqualsCanonicalizing(['/legacy'], $diff['removed']['endpoints']);
        $this->assertEqualsCanonicalizing(['Post'], $diff['added']['schemas']);
        $this->assertEqualsCanonicalizing(['Legacy'], $diff['removed']['schemas']);
        $this->assertSame(['email'], $diff['added']['fields']['User']);
        $this->assertSame(['name'], $diff['removed']['fields']['User']);
    }

    public function test_compare_with_latest_returns_error_when_no_versions_exist()
    {
        $result = $this->manager->compareWithLatest();

        $this->assertSame(['error' => 'No previous versions found'], $result);
    }

    public function test_generate_changelog_handles_changes_and_no_changes()
    {
        $changedMarkdown = $this->manager->generateChangelog([
            'added' => [
                'endpoints' => ['/posts'],
                'schemas' => ['Post'],
                'fields' => ['User' => ['email']],
            ],
            'removed' => [
                'endpoints' => ['/legacy'],
                'schemas' => ['Legacy'],
                'fields' => ['User' => ['name']],
            ],
            'modified' => [
                'endpoints' => [],
                'fields' => [],
            ],
        ]);

        $this->assertStringContainsString('## ➕ Added', $changedMarkdown);
        $this->assertStringContainsString('## ➖ Removed', $changedMarkdown);
        $this->assertStringContainsString('`/posts`', $changedMarkdown);
        $this->assertStringContainsString('~~`/legacy`~~', $changedMarkdown);
        $this->assertStringNotContainsString('No changes detected.', $changedMarkdown);

        $noChangesMarkdown = $this->manager->generateChangelog([
            'added' => ['endpoints' => [], 'schemas' => [], 'fields' => []],
            'removed' => ['endpoints' => [], 'schemas' => [], 'fields' => []],
            'modified' => ['endpoints' => [], 'fields' => []],
        ]);

        $this->assertStringContainsString('No changes detected.', $noChangesMarkdown);
    }

    public function test_prune_and_clear_manage_version_files()
    {
        File::ensureDirectoryExists($this->storageDir);

        File::put($this->storageDir . '/v_1.json', json_encode([
            'version' => '1',
            'timestamp' => '2026-01-03T00:00:00+00:00',
            'schema' => [],
        ]));
        File::put($this->storageDir . '/v_2.json', json_encode([
            'version' => '2',
            'timestamp' => '2026-01-02T00:00:00+00:00',
            'schema' => [],
        ]));
        File::put($this->storageDir . '/v_3.json', json_encode([
            'version' => '3',
            'timestamp' => '2026-01-01T00:00:00+00:00',
            'schema' => [],
        ]));

        $deletedByPrune = $this->manager->prune(1);
        $this->assertSame(2, $deletedByPrune);
        $this->assertCount(1, $this->manager->getVersions());

        $deletedByClear = $this->manager->clear();
        $this->assertSame(1, $deletedByClear);
        $this->assertCount(0, $this->manager->getVersions());
    }
}
