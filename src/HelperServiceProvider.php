<?php
namespace Yousheng\LaravelHelper;
use Illuminate\Support\ServiceProvider ;
use Yousheng\LaravelHelper\Helper;
use Yousheng\LaravelHelper\Console\MigrationsFromDatabaseCommand;
use Route;
use Help;
class HelperServiceProvider extends ServiceProvider{
    public function register(){
        $this->app->singleton('helper',function () {
            return new Helper($this->app);
        });
        $this->app->make(\Illuminate\Contracts\Http\Kernel::class)->prependMiddleware(\Yousheng\LaravelHelper\Http\Middleware\statistic::class);

/*        $this->app['migrations.from.database'] = function(){
                return new MigrationsFromDatabaseCommand;
        };

        $this->commands('migrations.from.database');*/


    }
    public function boot(){
        Help::statistic();
        $this->commands([
            MigrationsFromDatabaseCommand::class,
        ]);


    }


}