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

        // Check if params from TestRequest are present
        $params = collect($post['parameters']);

        $titleParam = $params->firstWhere('name', 'title');
        $this->assertNotNull($titleParam);
        $this->assertEquals('string', $titleParam['schema']['type']);
        $this->assertTrue($titleParam['required']); // "required" rule

        $countParam = $params->firstWhere('name', 'count');
        $this->assertNotNull($countParam);
        $this->assertEquals('integer', $countParam['schema']['type']); // "integer" rule
    }

    public function test_caching_commands()
    {
        $cachePath = base_path('bootstrap/cache/easy-doc.php');

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
