<?php

namespace Yousheng\LaravelHelper\Console;

use Illuminate\Console\GeneratorCommand;


class ScopeMakeCommand extends GeneratorCommand
{

    /**
     * The console command name.
     * example: php artisan make:scope PostActiveScope
     *
     * @var string
     */
    protected $signature = 'make:scope {name}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a new query scope class';

    /**
     * The type of class being generated
     * @var string
     */
    protected $type = 'Scope';

    /**
     * Get the stub file for the generator.
     *
     * @return string
     */
    protected function getStub()
    {
        return __DIR__.'/stubs/scope.stub';
    }

    /**
     * Get the default namespace for the class.
     *
     * @param string $rootNamespace
     *
     * @return string
     */
    protected function getDefaultNamespace($rootNamespace)
    {
        return $rootNamespace.'\Scopes';
    }
}
