<?php
namespace  Yousheng\LaravelHelper\Facades;
use Illuminate\Support\Facades\Facade;
use ReflectionClass;
use Route;
class Help extends Facade{
    public static function getFacadeAccessor(){
        return 'helper';
    }
    public static function statistic(){
        // list all sessions
        Route::prefix('yousheng')->group(function (){

            // list all routes
            Route::get('list-routes',function (){
                static::prettyArray(collect(Route::getRoutes()->getRoutes())->mapWithKeys(function($item){
                    return [$item->uri=>$item->getActionName()];
                })->all(),"List All Routes",1);
            });

            // list all langs
            Route::get('list-langs',function (){
                static::prettyArray(app('translation.loader')->namespaces(),"List All Langs");
            });

            // list all view paths
            Route::get('list-view-paths',function (){
                static::prettyArray(app('view.finder')->getPaths(),"List All View Paths");
            });

            // list all aliases
            Route::get('list-aliases',function (){
                static::prettyArray(\Illuminate\Foundation\AliasLoader::getInstance()->getAliases(),"List All View Aliases",1);
            });


            // list all publishes
            Route::get('list-publishes',function (){
                $reflectionServiceProvider = new \ReflectionClass(\Illuminate\Support\ServiceProvider::class);
                $publishes                 = $reflectionServiceProvider->getProperty('publishes');
                $publishGroups             = $reflectionServiceProvider->getProperty('publishGroups');
                $publishes->setAccessible(true);
                $publishGroups->setAccessible(true);
                $publishesValue            = $publishes->getValue(\Illuminate\Support\ServiceProvider::class);
                $publishGroupsValue        = $publishGroups->getValue(\Illuminate\Support\ServiceProvider::class);
                $publishes = \Illuminate\Support\ServiceProvider::pathsToPublish() ;
                static::prettyArray($publishesValue,"List Publishes",1);
                static::prettyArray($publishes,"List Publish Paths(from publishes)",1);
                static::prettyArray($publishGroupsValue,"List PublishGroups",1);
            });

            // list all sessions
            Route::get('list-sessions',function (){
                static::prettyArray(session()->all(),"List All Sessions",1);
            });
        });

    }

    /**
     * Print array pretty
     * @param $arr
     * @param null $title
     */

    public static function prettyArray($arr,$title=null,$sort=false){
       echo Array2str($arr,$title,$sort);
    }

    public static function Array2str($arr,$title=null,$sort=false){
        if(!is_array($arr)) return;
        $isOneDimensional=count($arr) == count($arr, 1);
        if ($isOneDimensional){
            $sort && ksort($arr);
            if(!empty($arr)){
                $maxKeyLength= collect(array_keys($arr))->max(function($item){
                    return strlen($item);
                }) ;
                $str = collect(array_keys($arr))->reduce(function($result,$key) use ($arr,$maxKeyLength){
                    return $result.'  '.str_pad($key,$maxKeyLength,' ')." => ".$arr[$key].PHP_EOL;

                },'');
            }else{
                $str='It\'s only a empty array';
            }
        }
        ob_start();
        echo "<pre>";
        echo $title?"<h2>".$title."</h2>":'';
        if($isOneDimensional){
            echo $str;
        }else{
            print_r($arr);
        }
        echo "</pre>";
        return ob_get_clean();
    }
}