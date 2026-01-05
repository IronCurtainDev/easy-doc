<?php

namespace EasyDoc\Tests\Feature;

use EasyDoc\Attributes\DocAPI;
use EasyDoc\Attributes\DocRequest;
use EasyDoc\Tests\TestCase;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;

class V2Test extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Define a route for testing
        Route::post('/v2-test', [TestV2Controller::class, 'store']);

        // Mock config
        Config::set('easy-doc.auto_discover_models', false);
        Config::set('easy-doc.base_path', '/');
    }

    public function test_doc_request_attribute_parses_rules()
    {
        $this->artisan('easy-doc:generate')
            ->assertExitCode(0);

        $outputPath = public_path('docs/openapi.json');
        $this->assertFileExists($outputPath);

        $content = json_decode(file_get_contents($outputPath), true);

        $this->assertArrayHasKey('/v2-test', $content['paths']);
        $post = $content['paths']['/v2-test']['post'];

        // Check if params from TestRequest are present in requestBody
        $this->assertArrayHasKey('requestBody', $post);

        $properties = $post['requestBody']['content']['application/json']['schema']['properties'];

        $this->assertArrayHasKey('title', $properties);
        $this->assertEquals('string', $properties['title']['type']);

        $this->assertArrayHasKey('count', $properties);
        $this->assertEquals('integer', $properties['count']['type']);

        // Check required fields
        $required = $post['requestBody']['content']['application/json']['schema']['required'];
        $this->assertContains('title', $required);
    }

    public function test_caching_commands()
    {
        $cachePath = base_path('bootstrap/cache/easy-doc.php');

        // Ensure directory exists for testing
        if (!File::exists(dirname($cachePath))) {
            File::makeDirectory(dirname($cachePath), 0755, true);
        }

        // clear first
        if (File::exists($cachePath)) {
            File::delete($cachePath);
        }

        // Run cache command
        $this->artisan('easy-doc:cache')
            ->assertExitCode(0);

        $this->assertFileExists($cachePath);

        // Verify content is a PHP array
        $data = require $cachePath;
        $this->assertIsArray($data);

        // Run clear command
        $this->artisan('easy-doc:clear')
            ->assertExitCode(0);

        $this->assertFileDoesNotExist($cachePath);
    }
}

class TestV2Controller
{
    #[DocAPI(name: 'V2 Test', group: 'V2')]
    #[DocRequest(TestRequest::class)]
    public function store() {}
}

class TestRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'title' => 'required|string|max:255',
            'count' => 'integer|min:0',
        ];
    }
}
