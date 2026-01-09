<?php

namespace EasyDoc\Tests;

use EasyDoc\Docs\DocBuilder;
use EasyDoc\Services\RouteDiscoveryService;
use Illuminate\Routing\Router;
use Illuminate\Http\Request;
use Orchestra\Testbench\TestCase as OrchestraTestCase;
use Mockery;

class UnresolvableDependency {
    public function __construct(string $arg) {}
}

class TestController
{
    public function index(UnresolvableDependency $dependency)
    {
        return 'success';
    }
}

class RouteDiscoveryServiceTest extends OrchestraTestCase
{
    protected function getPackageProviders($app)
    {
        return ['EasyDoc\EasyDocServiceProvider'];
    }

    public function testResolveMethodDependenciesFailWithNonNullable()
    {
        $router = $this->app->make(Router::class);
        $docBuilder = Mockery::mock(DocBuilder::class);

        $service = new class($router, $docBuilder) extends RouteDiscoveryService {
            public function exposeResolveMethodDependencies($reflection, $method, $request) {
                return $this->resolveMethodDependencies($reflection, $method, $request);
            }
        };

        $reflection = new \ReflectionClass(TestController::class);
        $request = Request::create('/');

        // This should now return a Mock object because Mockery is available in this test environment
        $deps = $service->exposeResolveMethodDependencies($reflection, 'index', $request);

        $this->assertCount(1, $deps);
        $this->assertInstanceOf(UnresolvableDependency::class, $deps[0]);
        $this->assertInstanceOf(\Mockery\MockInterface::class, $deps[0]);

        // Confirming it does NOT crash now
        try {
            $controller = new TestController();
            $result = $controller->index(...$deps);
            $this->assertEquals('success', $result);
        } catch (\TypeError $e) {
            $this->fail('Should NOT have thrown TypeError: ' . $e->getMessage());
        }
    }
}
