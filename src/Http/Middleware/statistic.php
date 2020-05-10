<?php


namespace Yousheng\LaravelHelper\Http\Middleware;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Route;
use Help;

class statistic
{
    public function handle(Request $request,$next)
    {
        $str="debugging...,current path is:".$request->path()."<br/><hr/>";
        $response = $next($request);
        // 低版本laravel无hasAny
       /* if(!$request->hasAny(['debug','getpublic'])){
            return $response;
        }*/

        if(!$request->has('debug') && !$request->has('getpublic')&& !$request->has('action')){
            return $response;
        }

        if($intercept=$request->debug){
            switch ($intercept){
                case 'aliases':
                    $str .= Help::Array2str(\Illuminate\Foundation\AliasLoader::getInstance()->getAliases(),"List All View Aliases",1);
                    break;
                case 'langs':
                    $str .= Help::Array2str(app('translation.loader')->namespaces(),"List All Langs");
                    break;
                case 'middleware':
                    // middle group 路由中间件
                    //$routeMiddleware=app(\Illuminate\Routing\Route::class)->gatherMiddleware();
                    // sorted middleware 排序后的中间件
                    $routeMiddleware=app('router')->gatherRouteMiddleware(app(\Illuminate\Routing\Route::class));
                    // global middleware 全局中间件
                    $properties = Help::getProperties(app(Kernel::class),1,'middleware');
                    $str.= Help::Array2str($properties['middleware'],"List Http Kernel Middleware(Global Middleware)",1);
                    $str.= Help::Array2str($routeMiddleware,"List Route Middleware",1);
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
                    $str .= Help::Array2str($publishesValue,"List Publishes",1);
                    $str.=Help::Array2str($publishes,"List Publish Paths(from publishes)",1);
                    $str.=Help::Array2str($publishGroupsValue,"List PublishGroups",1);
                    break;
                case 'routes':

                    $reflection = new \ReflectionClass(Route::getRoutes());
                    $routes                 = $reflection->getProperty('routes');
                    $routes->setAccessible(true);
                    $routesValue = $routes->getValue(Route::getRoutes());
                    $str .= Help::Array2str(collect(Arr::dot($routesValue))->mapWithKeys(function ($route,$key){
                        return [$key=>$route->getActionName()."  [".$route->getName()."]"];
                        })->filter(function($item,$key){
                            if(!Str::startsWith($key,'HEAD')){
                                return $item;
                            };
                    })->all(),"List All Routes,format: method.uri=>action [name]");

                    /*
                     * // not include method
                     * $str .= Help::Array2str(collect(Route::getRoutes()->getRoutes())->mapWithKeys(function($item){
                        return [$item->uri=>$item->getActionName()];
                    })->all(),"List All Routes");
                    */
                    break;
                case 'routes-detail':
                    $routeCollection = Route::getRoutes();
                    $routes = $routeCollection->getRoutesByMethod();
                    $allRoutes = Help::getProperties($routeCollection,1,'allRoutes');
                    $nameList = $routeCollection->getRoutesByName();
                    $actionList = Help::getProperties($routeCollection,1,'actionList');
                   /* $str = Help::Array2str($routes)->map(function($value,$key){
                        return [$item->uri=>$item->getActionName()];
                    })->all(),"List All Routes");
                    //dddd(app('routes')->getRoutes());*/
                    break;
                case 'sessions':
                    $str .= Help::Array2str(session()->all(),"List All Sessions",1);
                    break;
                case 'translations':
                    $fileLoader = app('translation.loader');
                    // hints also via app('translation.loader')->namespace();
                    $arr = Help::getProperty($fileLoader,['path','jsonPaths','hints']);
                    $str .= Help::Array2str(config('app.locale'),"default App Locale");
                    $str .= Help::Array2str($arr['path'],"List All translation's path");
                    $str .= Help::Array2str($arr['jsonPaths'],"List All translation's jsonPaths");
                    $str .= Help::Array2str($arr['hints'],"List All translation's namespace");
                    break;
                case 'views':
                    $str .= Help::Array2str(app('view')->getFinder()->getPaths(),"List All View Paths");
                    $str .= Help::Array2str(app('view')->getFinder()->getHints(),"List All View Namespaces");
                    $str .= Help::Array2str(app('view')->getFinder()->getViews(),"List All Views");
                    break;

                default:
                    $str.='no data to show';
                    break;
            }
        }

        // get all public method  of a class
        // example: http://www.bucaoxin.bendi/?getpublic=\Illuminate\Auth\SessionGuard
        if($className = $request->getpublic){
            if(class_exists($className)){
                $str.= '<pre>'.Help::getAllPublicMethods($className).'</pre>';
            }
        }

        // convenient than in console
        // 由于命令行不是很方便，改为这里处理一些动作，如清理缓存
        if($action = $request->action){
            switch ($action){
                case 'clear-config':
                    app('files')->delete(app()->getCachedConfigPath());
                    $str.= app()->getCachedConfigPath()."目录下的缓存清理完成";
                    break;
            }

        }





        $response->setContent($str);
        return $response;
    }
}