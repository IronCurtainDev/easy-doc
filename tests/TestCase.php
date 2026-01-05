<?php

namespace EasyDoc\Tests;

use EasyDoc\EasyDocServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

class TestCase extends Orchestra
{
    protected function getPackageProviders($app)
    {
        return [
            EasyDocServiceProvider::class,
        ];
    }
}
