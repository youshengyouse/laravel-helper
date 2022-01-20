<?php
namespace  Yousheng\LaravelHelper\Facades;
use Illuminate\Support\Facades\Facade;
use ReflectionClass;
use Route;
class Help extends Facade{
    public static function getFacadeAccessor(){
        return 'helper';
    }

}