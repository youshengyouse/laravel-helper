<?php


namespace Yousheng\LaravelHelper\Http\Middleware;
use Route;
use Help;

class statistic
{
    public function handle($request,$next)
    {
        $response = $next($request);
        if(!$request->has('debug')){
            return $response;
        }

        $lists = ['routes','views'];
        $intercept=$request->debug;
        switch ($intercept){
            case 'routes':
                $str = Help::Array2str(collect(Route::getRoutes()->getRoutes())->mapWithKeys(function($item){
                    return [$item->uri=>$item->getActionName()];
                })->all(),"List All Routes");
                break;
            case 'views':
                $str = Help::Array2str(app('view.finder')->getPaths(),"List All View Paths");
                break;
            case 'views':
                $str = Help::Array2str(app('translation.loader')->namespaces(),"List All Langs");
                break;
            case 'aliases':
                $str = Help::Array2str(\Illuminate\Foundation\AliasLoader::getInstance()->getAliases(),"List All View Aliases",1);
                break;

            case 'sessions':
                $str = Help::Array2str(session()->all(),"List All Sessions",1);
                break;
            case 'publishes':
                $reflectionServiceProvider = new \ReflectionClass(\Illuminate\Support\ServiceProvider::class);
                $publishes                 = $reflectionServiceProvider->getProperty('publishes');
                $publishGroups             = $reflectionServiceProvider->getProperty('publishGroups');
                $publishes->setAccessible(true);
                $publishGroups->setAccessible(true);
                $publishesValue            = $publishes->getValue(\Illuminate\Support\ServiceProvider::class);
                $publishGroupsValue        = $publishGroups->getValue(\Illuminate\Support\ServiceProvider::class);
                $publishes = \Illuminate\Support\ServiceProvider::pathsToPublish() ;

                $str = Help::Array2str($publishesValue,"List Publishes",1);
                $str.=Help::Array2str($publishes,"List Publish Paths(from publishes)",1);
                $str.=Help::Array2str($publishGroupsValue,"List PublishGroups",1);
                break;
            default:
                $str='no data to show';
                break;
        }
        $response->setContent($str);
        return $response;
    }
}