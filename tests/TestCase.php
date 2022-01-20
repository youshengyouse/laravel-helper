<?php

namespace Yousheng\LaravelHelper\Console\Tests;

use Yousheng\LaravelHelper\HelperServiceProvider;

class TestCase extends \Orchestra\Testbench\TestCase
{
    protected function getPackageProviders($app)
    {
        return [ScopeMakeCommandServiceProvider::class];
    }
}