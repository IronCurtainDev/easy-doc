<?php

namespace EasyDoc\Tests\Unit;

use EasyDoc\Docs\APICall;
use EasyDoc\Docs\Param;
use EasyDoc\Services\OpenApiConverter;
use EasyDoc\Tests\TestCase;

class OpenApiConverterTest extends TestCase
{
    public function test_build_parameters_uses_expected_locations_and_constraints_for_non_get_methods()
    {
        $apiCall = new APICall();
        $apiCall->addPathParam(new Param('id', Param::TYPE_INT, 'Resource ID'));
        $apiCall->addQueryParam((new Param('search', Param::TYPE_STRING, 'Search term'))->optional());
        $apiCall->setHeaders([Param::header('X-Trace-Id', 'Trace ID')]);
        $apiCall->setParams([
            (new Param('status', Param::TYPE_STRING, 'Status'))
                ->enum(['draft', 'published'])
                ->min(1)
                ->max(10)
                ->pattern('^[a-z]+$')
                ->defaultValue('draft')
                ->example('published'),
        ]);

        $parameters = (new OpenApiConverter())->buildParameters($apiCall, 'post');

        $id = collect($parameters)->firstWhere('name', 'id');
        $search = collect($parameters)->firstWhere('name', 'search');
        $trace = collect($parameters)->firstWhere('name', 'X-Trace-Id');
        $status = collect($parameters)->firstWhere('name', 'status');

        $this->assertSame('path', $id['in']);
        $this->assertSame('query', $search['in']);
        $this->assertSame('header', $trace['in']);
        $this->assertSame('formData', $status['in']);
        $this->assertSame(['draft', 'published'], $status['enum']);
        $this->assertSame(1, $status['minimum']);
        $this->assertSame(10, $status['maximum']);
        $this->assertSame('^[a-z]+$', $status['pattern']);
        $this->assertSame('draft', $status['default']);
        $this->assertSame('published', $status['example']);
    }

    public function test_build_parameters_defaults_location_to_query_for_get_methods()
    {
        $apiCall = new APICall();
        $apiCall->setParams([new Param('page', Param::TYPE_INT, 'Page number')]);

        $parameters = (new OpenApiConverter())->buildParameters($apiCall, 'get');
        $page = collect($parameters)->firstWhere('name', 'page');

        $this->assertSame('query', $page['in']);
    }

    public function test_build_responses_returns_defaults_when_no_examples_are_defined()
    {
        $responses = (new OpenApiConverter())->buildResponses(new APICall());

        $this->assertSame('Successful response', $responses['200']['description']);
        $this->assertSame('Unauthorized', $responses['401']['description']);
        $this->assertSame('Validation error', $responses['422']['description']);
    }

    public function test_build_responses_maps_custom_success_and_error_examples()
    {
        $apiCall = new APICall();
        $apiCall->setSuccessExample(['message' => 'ok'], 201, 'Created');
        $apiCall->setErrorExample(['error' => 'conflict'], 409, 'Conflict');

        $responses = (new OpenApiConverter())->buildResponses($apiCall);

        $this->assertSame('Created', $responses['201']['description']);
        $this->assertSame(['message' => 'ok'], $responses['201']['examples']['application/json']);
        $this->assertSame('Conflict', $responses['409']['description']);
        $this->assertSame(['error' => 'conflict'], $responses['409']['examples']['application/json']);
        $this->assertArrayNotHasKey('200', $responses);
        $this->assertArrayNotHasKey('401', $responses);
    }
}
