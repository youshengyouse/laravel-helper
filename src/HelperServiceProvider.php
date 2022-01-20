<?php
namespace Yousheng\LaravelHelper;
use Illuminate\Support\ServiceProvider ;
use Yousheng\LaravelHelper\Helper;
use Yousheng\LaravelHelper\Console\MigrationsFromDatabaseCommand;
use Yousheng\LaravelHelper\Console\ModelsFromDatabaseCommand;
use Yousheng\LaravelHelper\Console\ScopeMakeCommand;
use Route;
use Help;
use Yousheng\LaravelHelper\Console\DatabaseCreateCommand;

class HelperServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->app->singleton('helper', function () {
            return new Helper($this->app);
        });
        $this->app->make(\Illuminate\Contracts\Http\Kernel::class)->prependMiddleware(\Yousheng\LaravelHelper\Http\Middleware\Statistic::class);
        $this->app->make(\Illuminate\Contracts\Http\Kernel::class)->pushMiddleware(\Yousheng\LaravelHelper\Http\Middleware\Last::class);

        /*        $this->app['migrations.from.database'] = function(){
                        return new MigrationsFromDatabaseCommand;
                };

                $this->commands('migrations.from.database');*/
    }

    public function boot()
    {
        Help::statistic();
        $this->commands([
            DatabaseCreateCommand::class,
            MigrationsFromDatabaseCommand::class,
            ModelsFromDatabaseCommand::class,
            ScopeMakeCommand::class,
        ]);
    }
}