<?php

namespace EasyDoc\Tests\Unit;

use EasyDoc\Attributes\DocAPI;
use EasyDoc\Attributes\DocParam;
use EasyDoc\Services\AttributeReader;
use EasyDoc\Tests\TestCase;
use Illuminate\Http\Request;

class AttributeReaderTest extends TestCase
{
    public function test_it_reads_basic_doc_api_attributes()
    {
        $reader = new AttributeReader();
        $apiCall = $reader->readFromMethod(TestController::class, 'index');

        $this->assertNotNull($apiCall);
        $this->assertEquals('Test Index', $apiCall->getName());
        $this->assertEquals('Tests', $apiCall->getGroup());
    }

    public function test_it_reads_doc_param_attributes()
    {
        $reader = new AttributeReader();
        $apiCall = $reader->readFromMethod(TestController::class, 'index');

        $params = $apiCall->getParams();
        $this->assertCount(1, $params);
        $this->assertEquals('search', $params[0]['fieldName']);
        $this->assertEquals('string', $params[0]['dataType']);
    }
}

class TestController
{
    #[DocAPI(name: 'Test Index', group: 'Tests')]
    #[DocParam(name: 'search', type: 'string')]
    public function index(Request $request) {}
}
