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


    }
    public function boot(){


        Help::routes();


        /*

        $routes = collect($router->getRoutes()->getRoutes())->mapWithKeys(function($item){
            return [$item->uri=>$item->getActionName()];
        })->all();
        */

    }


}