<?php

namespace EasyDoc\Tests\Feature;

use EasyDoc\Attributes\DocAPI;
use EasyDoc\Attributes\DocParam;
use EasyDoc\Console\Commands\GenerateDocsCommand;
use EasyDoc\Services\AttributeReader;
use EasyDoc\Services\RouteDiscoveryService;
use EasyDoc\Tests\TestCase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Route;

class GeneratorTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Define a route for testing
        Route::get('/test-api', [TestApiController::class, 'index']);

        // Mock config
        Config::set('easy-doc.auto_discover_models', false);
        Config::set('easy-doc.base_path', '/');
    }

    public function test_it_generates_openapi_json()
    {
        // Run the command
        $this->artisan('easy-doc:generate')
            ->assertExitCode(0);

        // Check if file exists
        $outputPath = public_path('docs/openapi.json');
        $this->assertFileExists($outputPath);

        // Verify content
        $content = json_decode(file_get_contents($outputPath), true);

        $this->assertArrayHasKey('openapi', $content);
        $this->assertEquals('3.0.3', $content['openapi']);

        // Check paths
        $this->assertArrayHasKey('/test-api', $content['paths']);
        $this->assertArrayHasKey('get', $content['paths']['/test-api']);

        $operation = $content['paths']['/test-api']['get'];
        $this->assertEquals('Test API', $operation['summary']);
        $this->assertEquals('General', $operation['tags'][0]);

        // Check params
        $this->assertCount(1, $operation['parameters']);
        $this->assertEquals('filter', $operation['parameters'][0]['name']);
    }
}

class TestApiController
{
    #[DocAPI(name: 'Test API', group: 'General')]
    #[DocParam(name: 'filter', type: 'string', location: 'query')]
    public function index() {}
}
