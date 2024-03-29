<?php
/**
 * ----------------------------------------------------------------------
 * 开发助手.功能升级中 A developer helper for laravel                                                                                  |
 * ----------------------------------------------------------------------
 * Copyright (c) 2017 深圳有声有色网络科技有限公司
 * ----------------------------------------------------------------------
 * Author: daqi <2922800186@qq.com>
 * ----------------------------------------------------------------------
 */

namespace Yousheng\LaravelHelper;

use Exception;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use ReflectionProperty;
use Route;

class Helper
{
    protected $container;
    protected $files;
    public static $tmpl = <<<EOT
<!DOCTYPE html>
<html lang="en" class="h-full font-sans antialiased">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=1280">
    <title>code debug|backtrace|show object</title>
    <style type="text/css">
      pre{background-color: #056D6D;color: #fff; }
      pre.bg1{background-color: #056D6D;}
      pre.bg2{background-color: #f00;}
      pre.bg4{background-color: #f90;}
      pre.bg3{background-color: #00f;}
      pre.bg5{background-color: #000;}
      span.line{color:#209A9E;}
    </style>
    </head>
    <body>
        <div>
            <pre class="bg1">%s</pre>
            <![CDATA[PLACEHOLDER]]
        </div>
    </body>
</html>
EOT;
    public static $maxLevel = 4;
    public static $currentLevel = 0;
    public static $defaultLevel = 0;
    public static $maxStrLength= 1024*200000; // 200M
    public static $maxMemory= '1000M';        // 1G

    public function __construct($container,$files=null)
    {
        $this->container = $container;
        $this->files = $files??app('files');
    }


    public function print2file($item, $append = false, $dirname="0000",$file = "index.html", $maxLevel = null)
    {
        ini_set('memory_limit', static::$maxMemory??'200M');
        if ($maxLevel) {
            static::$maxLevel = $maxLevel;
        }
        static::$currentLevel = static::$defaultLevel;

        $debugArray = debug_backtrace();
        $debugFile = $debugArray[0]['file'];
        $debugLine = $debugArray[0]['line'];

        $saveDir = $_SERVER['DOCUMENT_ROOT'] . "/{$dirname}";

        if(substr(PHP_SAPI_NAME(),0,3) === 'cli'){
            $saveDir = getcwd(). "/public/{$dirname}";
        }


        $this->files->ensureDirectoryExists($saveDir);
        $saveFile = $saveDir . DIRECTORY_SEPARATOR . $file;

        // todo level 只打到第几级，数组已ok，但对象稍麻烦，待完善
        //$item = $this->printItem($item);

        ob_start();

        $before = memory_get_usage();
        print_r($item);
        $after = memory_get_usage();
        $memUsed = $after - $before;
        // 若ini配置的memory_limit(内存限制) 大于 AG(allocated memory),就报错
        $content = ob_get_contents();
        ob_clean();
        $content=$this->cleanPrint_rString($content);
        $content= mb_substr($content,0,static::$maxStrLength);
        $content = "\n\r...File:" . $debugFile . '__Line:' . $debugLine . "\n\r".$content. "\n\r";

        // todo html template to pretty show
        //$content = sprintf(static::$tmpl, $content);

       // echo "\n .... p2f函数 正在向 $saveFile  写调试内容 ...  \n";
        if ($append) {
            $this->files->append($saveFile, $content);
        } else {
            $this->files->put($saveFile, $content);
        }

    }
    public function dump($item, $append = false, $file = "index.html")
    {
        $debugArray = debug_backtrace();
        $debugFile = $debugArray[2]['file'];
        $debugLine = $debugArray[2]['line'];
        $saveDir = $_SERVER['DOCUMENT_ROOT'] . "/0000";
        $this->files->ensureDirectoryExists($saveDir);
        $saveFile = $saveDir . DIRECTORY_SEPARATOR . $file;
        ob_start();
        \Symfony\Component\VarDumper\VarDumper::dump($item);
        $content = ob_get_clean();
        $content = "File:____" . $debugFile . '    => Line:____' . $debugLine . "\n".$content;
        if ($append) {
            $this->files->append($saveFile, $content);
        } else {
            $this->files->put($saveFile, $content);
        }

    }

    // todo
    protected function obHandler($string,$flags){
        // flags为1，表示开始，2表示，8表示结束
        static $input = array();
        $flags_sent=[];
        if ( $flags & PHP_OUTPUT_HANDLER_START )
            $flags_sent[] = "PHP_OUTPUT_HANDLER_START_开始";
        if ( $flags & PHP_OUTPUT_HANDLER_CONT )
            $flags_sent[] = "PHP_OUTPUT_HANDLER_CONT_内容";
        if ( $flags & PHP_OUTPUT_HANDLER_END )
            $flags_sent[] = "PHP_OUTPUT_HANDLER_END_结束";
        $input[] = implode(' | ', $flags_sent) . " ((($flags))): $string<br />";
        $output  = "$string<br />";
        if ( $flags & PHP_OUTPUT_HANDLER_END ) {
            $output .= '<br />';
            foreach($input as $k => $v) $output .= "$k: $v";
        }
        return $output;
    }


    // todo 缩小代码，更适合浏览
    public function cleanPrint_rString($str)
    {
        $str = preg_replace('/\*RECURSION\*/','',$str);                  // 去掉 *RECURSION* 这种单独的一行
        $str = preg_replace('/(\n)\s*\n/','$1',$str);                    // 去掉空行，并去掉多余的行首空格
        $str = preg_replace('/Array\n\s*?\(\n\s*\)/','Array()',$str);    // 空数组由三行合并为一行
        $str = preg_replace('/\n\(/','(',$str);                          // 将第2行的左括号移到第1行
        return $str;
    }

    // todo 对象部分未找到找的办法，暂时使用print_r来直接打印
    protected function printItem($item)
    {
        static::$currentLevel++;
        $isEndLeaf = false;
        echo "当前是第" . static::$currentLevel . "级__总共" . static::$maxLevel . "<br/>";
        if (static::$currentLevel >= static::$maxLevel) $isEndLeaf = true;

        if ($item instanceof \Closure) {
            $item = 'A closure function';
        } elseif (is_array($item) || is_object($item)) {
            if(is_object($item)){
                // 如何深拷贝，是一个问题
                $tmp = serialize($item);
                $item = unserialize($tmp);
                $this->setAllPropertiesAccessiable($item);
            }

            if ($isEndLeaf) {
                $this->handleEndLeaf($item);
            }
            foreach ($item as $property => $value) {
                echo "当前处理".$property."<br/>";
                if (is_array($value) || is_object($value)) {
                    $this->printItem($value);
                }
            }
        }
        static::$currentLevel--;
        return $item;
    }

    // 处理最末端的数组和对象
    protected function handleEndLeaf($item)
    {
        if (is_array($item) || is_object($item)) {
            foreach ($item as $k => &$v) {
                if (is_array($v)) {
                    $v = '...AN ARRAY';
                } elseif ($v instanceof \Closure) {
                    $v = '...AN ANONYMOUS FUNCTION';
                } elseif (is_object($v)) {
                    $v = '...' . get_class($v) . ' OBJECT';
                } else {
                    continue;
                }
            }
        }
        return $item;
    }

    // 设置所有属性可访问
    public function setAllPropertiesAccessiable($object)
    {
        if (!is_object($object)) return;
        $reflection = new \ReflectionObject($object);
        $properties = $reflection->getProperties(\ReflectionProperty::IS_PUBLIC | \ReflectionProperty::IS_PROTECTED | \ReflectionProperty::IS_PRIVATE);
        foreach ($properties as $property) {
            $property->setAccessible(true);
        }
    }



    /**
     *  get all public methods from a classname or a object
     *  获取对象的所有public方法，适合代码分析用
     *  example1：   print_r( \Help::getAllPublicMethods(\Illuminate\Support\Collection::class) ) ;
     *  example2：   print_r( \Help::getAllPublicMethods(app('files')  );
     *  example2     http://database.study/?getpublic=\Illuminate\Http\Request
     *  原来没有包含trait中的部分，2020-4-24补充 use trait部分
     * @param $class
     * @return mixed
     * @throws \ReflectionException
     */

    public function getAllPublicMethods($class)
    {
        # 1.类继承链：求出当前类及所有父类组成的数组[当前类，父类，爷类...]
        $chains = static::getChain()($class);

        # 2.类和trait组成的数组，考虑到每个类可能有多个trait
        $all=collect($chains)->map(function($chain){
            $reflectionClass = new \ReflectionClass($chain);
            $traits = collect($reflectionClass->getTraits())->map(function($item){
                return $item->getName();
            })->values()->all();
            return array_merge($traits,[$chain]);
        })->collapse()->all();

        $tmp=[];

        foreach ($all as $class) {
            $content = $this->files->get($this->getClassFileName($class)); //读取类文件的内容
            $pattern1 = '/\s*,\s*\n/m';                                    // 参数多行并一行
            $pattern2 = '/^((?!public).)*$/m';                             // 删除不包含 `public`的行
            $pattern3 = '/^\s+/m';                                         // 去行首空格及空行
            $pattern4 = '/public\s+(static\s)?\s*function\s+/';            // 去掉public function
            $pattern5 = '/ {2,}/';                                         // 多个空格合成一个空格

            $content = preg_replace($pattern1, ',', $content);
            $content = preg_replace($pattern2, '', $content);
            $content = preg_replace($pattern3, '', $content);
            $content = preg_replace($pattern4, '$1', $content);
            $content = preg_replace($pattern5, ' ', $content);
            $lines = explode("\n", $content);
            $tmp[$class]=$lines;
        }
        // 清除父类中被重写的方法
        $compares = []; //用来比较是否有重写方法，思路是，先将子类的方法放在这里，在遍历父类中是否有此方法，有的话清除
        foreach ($tmp as $className=>&$methods){
            foreach ($methods as $key=>$method){
                // 清除子类中已有的方法，子类排在父类的前面
                if(!$method){
                    unset($methods[$key]);
                    continue;
                }
                if(in_array($method,$compares,true)){
                    unset($methods[$key]);
                }else{
                    $compares[]=$method;
                }
            }
        }
        //
        $flat = [];
        foreach ($tmp as $k=>$v){
            foreach($v as $sub){
                $flat[$sub]=$k;
            }
        }
        ksort($flat);

        foreach ($flat as $key => $value) {                // 删除空值及将静态方法移到最后
            if (Str::contains($key, 'static')) {
                unset($flat[$key]);
                $flat[$key]=$value;
                //array_push($flat, $value);
            }
        }
        return $this->Array2str($flat);
        /*return collect(array_values($array))->unique()->reduce(function ($result, $item) {
            return $result . $item . "\n";

        });*/
    }


    /**
     * 获取类继承链
     * 用法 $a = myClass::getChain()()
     * 用法 $b = myClass::getChain()('theClass')
     * @return \Closure
     */
    public static function getChain() {
        $chain = [];
        return $function = function($className='') use (& $chain, & $function) {
            if (empty($className))
                $className = static::class;

            if (empty($chain))
                $chain[] = $className;

            $parent = get_parent_class($className); //没有父类时返回false

            if ($parent !== false) {
                $chain[]= $parent;
                return $function($parent);
            }
            return $chain;
        };
    }


    /**
     * 根据对象或类名来获取文件名
     * @param $className
     * @return string
     * @throws \ReflectionException
     */

    public function getClassFileName($className)
    {
        if (is_object($className)) {
            $className = get_class($className);
        }
        $classReflection = new \ReflectionClass($className);
        return $classReflection->getFileName();
    }

    /**
     * 单独定义路由来显示各种统计信息，这个方便，但有一缺点，不能读取其它路由中注册的服务提供商等，建议改用中间件 xxxx/xxx?debug=routes的形式
     */
    
    //改为中间件实现，闭包路由不能被缓存
    public function statistic()
    {
        // list all sessions
        Route::prefix('yousheng')->group(function () {

            // list all routes
            Route::get('list-routes', function () {
                static::prettyArray(collect(Route::getRoutes()->getRoutes())->mapWithKeys(function ($item) {
                    return [$item->uri => $item->getActionName()];
                })->all(), "List All Routes", 1);
            });

            // list all langs
            Route::get('list-langs', function () {
                static::prettyArray(app('translation.loader')->namespaces(), "List All Langs");
            });

            // list all view paths
            Route::get('list-view-paths', function () {
                static::prettyArray(app('view')->getFinder()->getPaths(), "List All View Paths");
                static::prettyArray(app('view')->getFinder()->getHints(), "List All View Namespaces");
                static::prettyArray(app('view')->getFinder()->getViews(), "List All Views");
            });

            // list all aliases
            Route::get('list-aliases', function () {
                static::prettyArray(\Illuminate\Foundation\AliasLoader::getInstance()->getAliases(), "List All View Aliases", 1);
            });


            // list all publishes
            Route::get('list-publishes', function () {
                $reflectionServiceProvider = new \ReflectionClass(\Illuminate\Support\ServiceProvider::class);
                $publishes = $reflectionServiceProvider->getProperty('publishes');
                $publishGroups = $reflectionServiceProvider->getProperty('publishGroups');
                $publishes->setAccessible(true);
                $publishGroups->setAccessible(true);
                $publishesValue = $publishes->getValue(\Illuminate\Support\ServiceProvider::class);
                $publishGroupsValue = $publishGroups->getValue(\Illuminate\Support\ServiceProvider::class);
                $publishes = \Illuminate\Support\ServiceProvider::pathsToPublish();
                static::prettyArray($publishesValue, "List Publishes", 1);
                static::prettyArray($publishes, "List Publish Paths(from publishes)", 1);
                static::prettyArray($publishGroupsValue, "List PublishGroups", 1);
            });

            // list all sessions
            Route::get('list-sessions', function () {
                static::prettyArray(session()->all(), "List All Sessions", 1);
            });
        });

    }
    

    /**
     * 漂亮打印数组，以字符串的形式显示
     */

    public function prettyArray($arr, $title = null, $sort = false)
    {
        echo $this->Array2str($arr, $title, $sort);
    }


    public function Array2str($arr, $title = null, $sort = false)
    {
        $arr = Arr::wrap($arr);
        $isOneDimensional = count($arr) == count($arr, 1);   // 判断是否是一维数组
        if ($isOneDimensional) {
            $sort && ksort($arr);
            if (!empty($arr)) {
                // 读取数组中最长的键的长度
                $maxKeyLength = collect(array_keys($arr))->max(function ($item) {
                    return strlen($item);
                });
                //
                $str = collect(array_keys($arr))->reduce(function ($result, $key) use ($arr, $maxKeyLength) {
                    // chop否则会在html换行
                    if($arr[$key] instanceof \Closure){
                        $arr[$key]=$this->closure_dump($arr[$key]);
                    }
                    return $result . str_pad(chop($key), $maxKeyLength, '.') . " => " . $arr[$key] . PHP_EOL;
                }, '');
            } else {
                $str = 'It\'s only an empty array';
            }
        }
        ob_start();
        echo "<pre style='background-color: aliceblue;width:100%'>\n";
        echo $title ? "<h2>" . $title . "</h2>" : '';
        if ($isOneDimensional) {
            echo $str;
        } else {
            print_r($arr);
        }
        echo "\n</pre>";
        return ob_get_clean();
    }

    // 打印闭包的代码

    public function closure_dump($c) {
        if(! $c instanceof \Closure){
            return ;
        }
        $str = 'function (';
        $r = new \ReflectionFunction($c);
        $params = array();
        foreach($r->getParameters() as $p) {
            $s = '';
            if($p->isArray()) {
                $s .= 'array ';
            } else if($p->getClass()) {
                $s .= $p->getClass()->name . ' ';
            }
            if($p->isPassedByReference()){
                $s .= '&';
            }
            $s .= '$' . $p->name;
            if($p->isOptional()) {
                $s .= ' = ' . var_export($p->getDefaultValue(), TRUE);
            }
            $params []= $s;
        }
        $str .= implode(', ', $params);
        $str .= '){' . PHP_EOL;
        $lines = file($r->getFileName());
        for($l = $r->getStartLine(); $l < $r->getEndLine(); $l++) {
            $str .= $lines[$l];
        }
        return $str;
    }


    /**
     * 读取文件的代码，待完善
     * @return bool|string
     */
    protected function _fetchCode()
    {
        // Open file and seek to the first line of the closure
        $file = new SplFileObject($this->reflection->getFileName());
        $file->seek($this->reflection->getStartLine()-1);

        // Retrieve all of the lines that contain code for the closure
        $code = '';
        while ($file->key() < $this->reflection->getEndLine())
        {
            $code .= $file->current();
            $file->next();
        }

        // Only keep the code defining that closure
        $begin = strpos($code, 'function');
        $end = strrpos($code, '}');
        $code = substr($code, $begin, $end - $begin + 1);

        return $code;
    }

    /**
     * 获取对象的某些属性，适合显示对象的指定属性
     * @param $obj
     * @param int $level
     * @param string $proName
     * @param array $exludes
     * @return array
     * @throws \ReflectionException
     */
    public function getProperties($obj, $level = 1, $proName = 'all', $exludes = [])
    {
        $level = (int)$level;
        if ($level < 1 || $level > 3) {
            throw new Exception('暂时只支持1-3级');
        }
        $reflect = new \ReflectionObject($obj);

        // 获取所有属性的值
        $props = [];
        if ($proName === 'all') {
            $props = $reflect->getProperties(ReflectionProperty::IS_PUBLIC | ReflectionProperty::IS_PROTECTED | ReflectionProperty::IS_PRIVATE | ReflectionProperty::IS_STATIC);
        } else {
            $targets = array($proName);
            foreach ($targets as $pr) {
                $props[] = $reflect->getProperty($pr);
            }
        }
        //排除那些属性
        if(is_string($exludes)){
            $exludes=array($exludes);
        }
        if (!empty($exludes) && is_array($exludes)) {
            foreach ($props as $k => $v) {
                if (in_array($v->getName(), $exludes)) {
                    unset($props[$k]);                }
            }
        }

        $_tmp = array();
        $_sublevel = $level - 1;
        foreach ($props as $t => $pro) {
            $pro->setAccessible(true);
            $name = $pro->getName();
            $value = $pro->getValue($obj);
            if ($level == 1) {
                if (is_object($value)) $value = get_class($value) . 'OBJECT';
                if (is_array($value)) {
                    foreach ($value as $k => &$v) {
                        if (is_object($v)) $v = get_class($v) . 'OBJECT';
                    }
                }
            } else {
                if (is_object($value)) $value = getProperties($value, $_sublevel);
                if (is_array($value)) {
                    foreach ($value as $k => &$v) {
                        if (is_object($v)) $v = getProperties($v, $_sublevel);
                    }
                }
            }

            $_tmp[$name] = $value;
        }
        return $_tmp;

    }

    // get a protected or private property's value on an object
    // 获取一个对象的保护或私有属性的值
    public function getProperty($object,$propertyName){
        $reflection = new \ReflectionClass($object);
        $names = (array)($propertyName);
        $return=[];
        foreach ($names as $name){
            $property  = $reflection->getProperty($name);
            $property->setAccessible(true);
            $return[$name]=$property->getValue($object);
        }
        return $return;
    }





}