<?php
namespace Yousheng\LaravelHelper;
use Illuminate\Support\ServiceProvider ;
use Yousheng\LaravelHelper\Helper;
use Route;
use Help;
class HelperServiceProvider extends ServiceProvider{
    public function register(){
        $this->app->singleton('helper',function () {
            return new Helper($this->app);
        });
        $this->app->make(\Illuminate\Contracts\Http\Kernel::class)->prependMiddleware(\Yousheng\LaravelHelper\Http\Middleware\statistic::class);


    }
    public function boot(){
        Help::statistic();
        $this->commands([]);


    }


}