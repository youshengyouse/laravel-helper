<?php
// +----------------------------------------------------------------------+
// | 调试助手.开发过程中使用。不要出现在上线产品中.                           |                                                    |
// +----------------------------------------------------------------------+
// | Copyright (c) 2017 深圳有声有色网络科技有限公司                        |
// +----------------------------------------------------------------------+
// | 作者: daqi <768287201@qq.com>                                        |
// +----------------------------------------------------------------------+
ini_set('display_errors', 'On');
error_reporting(E_ALL);


/*$html_begin = <<<EOT
		<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
		<html xmlns="http://www.w3.org/1999/xhtml">
		<head>
		<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
		<title>代码调试|函数调用追踪|查看对象</title>
		</head>
		<body>
			<div><pre style='background-color:#f60'>
EOT;

$html_end = <<<EOD
	</pre></div></body></html>
EOD;*/


/**
 * @param $obj
 * @param int $level
 * @param string $proName
 * @param array $exludes
 * @return array
 * 用途: 获取某个对象的所有属性(1-3级)，包括私有属性，暂时只支持3级以下
 * 用法:
 *      g(对象，1，[属性1，属性2，属性3])
 *      g(对象，1，'all',['属性1'，‘属性2']) 排除属性1和属性2
 */
function g($obj, $level = 1, $proName = 'all', $exludes = [])
{
    return _getProperties($obj, $level, $proName, $exludes);
}


/**
 * @param $var
 * @param bool $append
 * @param string $filename
 * 用途: 以页面的形式打印内容，调试用
 */
function p2f($var, $append = false, $filename = "1.html")
{
    $TPL = <<<EOT
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
    <head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <title>代码调试|函数调用追踪|查看对象</title>
    <style type="text/css">
      pre{
          border:"1px solid #f00";
          background-color: #056D6D;
          color: #fff;
      }
      pre.bg1{
        background-color: #056D6D;
      }
      pre.bg2{
        background-color: #f00;
      }
      pre.bg4{
        background-color: #f90;
      }
      pre.bg3{
        background-color: #00f;
      }
      pre.bg5{
        background-color: #000;
      }
      span.line{
        color:#209A9E;
      }
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
    //global $html_begin,$html_end;
    ob_start();
    //echo $html_begin;
    //todo:静态属性
    // echo "类型是:".gettype($var)."\n";
    if (is_object($var)) {
        echo "\n\n\n对象的类名是:" . classfile($var) . "\n";

        switch ($var) {
            case $var instanceof \Zend\Expressive\Middleware\LazyLoadingMiddleware:
                $_var = clone $var;
                setPrivatePropertyNull($_var, 'container');
                print_r($_var, false);
                break;
            case $var instanceof \Zend\Stratigility\Next:
                $next = clone $var;
                $reflect = new \ReflectionObject($next);
                $prop = $reflect->getProperty('queue');
                $prop->setAccessible(true);
                $pl = $prop->getValue($next); // SplQueue
                $count = $pl->count();
                echo "\n共有" . $count . "个中间件\n";
                $index = 1;
                $pl->rewind();
                while ($pl->valid()) {
                    //echo "\n${index}-中间件类名:" . get_class($pl->current()) . "\n\n";
                    //echo "\n${index}-中间件文件名:" . classfile($pl->current()) . "\n\n";
                    if ($pl->current() instanceof \Zend\Expressive\Middleware\LazyLoadingMiddleware) {
                        setPrivatePropertyNull($pl->current(), 'container');
                    }
                    $index++;
                    $pl->next();
                };
                print_r($var, false);
                break;
            case $var instanceof \Zend\Stratigility\MiddlewarePipe:
                $middlewarePipe = clone $var;
                $reflect = new \ReflectionObject($middlewarePipe);
                $prop = $reflect->getProperty('pipeline');
                $prop->setAccessible(true);
                $pl = $prop->getValue($middlewarePipe); // SplQueue
                $count = $pl->count();
                echo "\n共有" . $count . "个中间件\n";
                $index = 1;
                $pl->rewind();
                while ($pl->valid()) {
                    //echo "\n${index}-中间件类名:" . get_class($pl->current()) . "\n\n";
                    //echo "\n${index}-中间件文件名:" . classfile($pl->current()) . "\n\n";
                    if ($pl->current() instanceof \Zend\Expressive\Middleware\LazyLoadingMiddleware) {
                        setPrivatePropertyNull($pl->current(), 'container');
                    }
                    $index++;
                    $pl->next();
                };
                print_r($var, false);
                break;
            case $var instanceof \Zend\Router\RouteMatch:
                $params = $var->getParams();
                $middleware = $params['middleware'] ?: null;
                // 中间件处理
                if ($middleware) {
                    if ($middleware instanceof \Zend\Stratigility\MiddlewarePipeInterface) {
                        // 中间件管道
                        $middlewarePipe = clone $middleware;
                        $reflect = new \ReflectionObject($middlewarePipe);
                        $prop = $reflect->getProperty('pipeline');
                        $prop->setAccessible(true);
                        $pl = $prop->getValue($middlewarePipe);
                        $pl->rewind();
                        while ($pl->valid()) {
                            $currentMiddleware = $pl->current(); //待处理的中间件
                            if ($currentMiddleware instanceof \Zend\Expressive\Middleware\LazyLoadingMiddleware) {
                                $reflectLazyMiddleware = new \ReflectionObject($currentMiddleware);
                                $propContainer = $reflectLazyMiddleware->getProperty("container");
                                $propContainer->setAccessible(true);
                                $propContainer->setValue($currentMiddleware, get_class($propContainer->getValue($currentMiddleware)) . "的实例");
                            }
                            $pl->next();
                        }
                    } elseif ($middleware instanceof \Psr\Http\Server\MiddlewareInterface) {
                        // 中间件
                        if ($middleware instanceof \Zend\Expressive\Middleware\LazyLoadingMiddleware) {
                            $reflectLazyMiddleware = new \ReflectionObject($middleware);
                            $propContainer = $reflectLazyMiddleware->getProperty("container");
                            $propContainer->setAccessible(true);
                            $propContainer->setValue($middleware, get_class($propContainer->getValue($middleware)) . "的实例");
                        }
                    }
                }
                break;
            default:
                print_r($var, false);

        }

    } else {
        print_r($var, false);
    }

    $debug_arr = debug_backtrace();
    echo _rootalias("\n<span class='line'>调试位置:" . $debug_arr[0]['file'] . $debug_arr[0]['line'] . "</span>\n\n\r\n");
//echo $html_end;
    $info = ob_get_contents();
    ob_end_clean();

    $info = _clean($info);
    $saveDir = $_SERVER['DOCUMENT_ROOT'] . "/0000";
//$fullName  = $saveDir."/".$filename;

    is_dir($saveDir) || mkdir($saveDir, 0777); //如果文件夹不存在，先建文件夹
    $saveFile = $saveDir . DIRECTORY_SEPARATOR . $filename; //如果文件不存在，先新建一个再使用file_put_contents

    $content = sprintf($TPL, $info);


    if (!$append || !is_file($saveFile)) {
        if (($TxtRes = fopen($saveFile, "w+")) === FALSE) {
            echo("创建可写文件：" . $filename . "失败");
            exit();
        }
        $content = preg_replace("/(\<\/body\>)/", "\n1$1", $content); // 加一个计数
        file_put_contents($saveFile, $content);
    } else {
        $old_content = file_get_contents($saveFile);
        preg_match('@\n(?P<counter>\d)\<\/body\>@', $old_content, $matches);
        $counter = ($matches['counter'] ?? 0) + 1;
        if ($counter > 5) {
            $counter = 1;
        }
        $old_content = preg_replace("/(\n)(\d)(\<\/body\>)/", "\n${counter}</body>", $old_content); // 加一个计数
        $content = str_replace("<![CDATA[PLACEHOLDER]]", "<pre class='bg${counter}'>" . $info . " </pre><![CDATA[PLACEHOLDER]]", $old_content);
        file_put_contents($saveFile, $content);
    }
}

/**
 * @param $object
 * @param $p
 * 删除对象的私有属性
 */
function setPrivatePropertyNull($object, string $p)
{
    $reflect = new \ReflectionObject($object);
    $prop = $reflect->getProperty($p);
    $prop->setAccessible(true);
    $prop->setValue($object, get_class($prop->getValue($object)) . "对象");
}

/**
 * @param bool $append
 * @param string $path
 * 用途： 打印函数的调用回溯信息
 */
function d2f($short = false, $append = false, $filename = '2.html', $level = null)
{
    ini_set('max_execution_time', '100');
    global $html_begin, $html_end;
    ob_start();
    echo $html_begin;
    $debug_arr_all = debug_backtrace();
    array_shift($debug_arr_all);//去掉d2f()
    $reverse = array_reverse($debug_arr_all);

    // 取显示多少步
    if ($level) {
        $debug_arr = array_slice($reverse, (-1) * $level);
    } else {
        $debug_arr = $reverse;
    }


    $shortArray = array();
    if ($short) {
        foreach ($debug_arr as $key => $val) {
            $shortArray[$key] = _rootalias($val['file']) . '____' . $val['line'] . "行";
        }
        print_r($shortArray);
    } else {


        foreach ($debug_arr as $key => &$val) {
            $val['file'] = _rootalias($val['file']) . '____' . $val['line'] . "行";
            // 简单模式，只保留文件行，

            if (isset($val['type'])) {
                if ('::' === $val['type']) { //表示是静态方法
                    // $val['b'] = '类'.$val['class'].'中的静态方法'.$val['function']." ，定义类的文件是：".classfile($val['class']);
                    $val['class_file'] = classfile($val['class']) . '____静态方法' . $val['function'] . "()";
                } elseif ('->' === $val['type']) {
                    if (get_class($val['object']) !== $val['class']) {
                        // $val['b'] = '类'.$val['class'].'中的方法'.$val['function']." ，定义类的文件是：".classfile($val['class']);
                        $val['file_object'] = classfile(get_class($val['object']));
                        $val['file_class'] = classfile($val['class']);
                        $_object = clone $val['object'];
                        $val['object'] = get_class($_object);
                        $val['class'] .= '中定义方法____' . $val['function'] . "()";


                    } else {
                        //$val['b'] = '类'.get_class($val['object']).'中的方法'.$val['function']." ，定义类的文件是：".classfile(get_class($val['object']));
                        $val['class_file'] = classfile($val['class']);
                        $_object = clone $val['object'];
                        $val['object'] = get_class($_object) . '中定义方法____' . $val['function'] . "()";
                        unset($val['class']); //没有继承的类，不用保留
                    }
                }
            } else {
                $val['file'] .= "____" . $val['function'] . "()";
            }
            if (empty($val['args'])) {
                $val['args'] = '[]';

            }

            _unset('line', $val);
            _unset('function', $val);
            _unset('type', $val);


            foreach ($val as $key1 => &$val1) {
                if (is_string($val1)) {
                    $val1 = _rootalias($val1);
                }

                // if($key1=='object') $val[$key1]=get_class($val1).'对象实例';
                if ($key1 == 'args' && is_array($val1)) {
                    foreach ($val1 as $key2 => &$val2) {
                        if (is_string($val2)) {
                            $val1 = _rootalias($val1);
                        }
                        if (is_object($val2)) {
                            $val1[$key2] = get_class($val2) . '对象实例';
                        }
                        if (is_array($val2)) {
                            foreach ($val2 as $key3 => $val3) {
                                if (is_object($val3)) {
                                    $val2[$key3] = get_class($val3) . '对象实例';
                                }
                            }
                        }
                    }
                }


            }
            $i = $val['args'];
            unset($val['args']);
            $val['args'] = $i;
            //array_push($val,$i);
        }

        //


        /*
        待完成，转为字符串，使输出更美观
            $outstr = '';
            $step =1;
            foreach($debug_arr as $key => $val){
                $outstr .= "第".$step."步<br/>\n";
                $outstr .="\t文件=>". $val['file']."<br/>\n";
                $outstr .=isset($val['object'])?"\t对象=>".$val['object']."<br/>\n":'';
                $outstr .=isset($val['class_file'])?"\t类文件=>".$val['class_file']."<br/>\n":'';
                $outstr .=empty($val['args'])?"\t参数=>".$val['args']."<br/>\n":'';
                $step++;
            }

         */


        print_r($debug_arr); // 20190311增加反向，并去掉最后d2f处
    }
    echo $html_end;
    $info = ob_get_contents();
    ob_end_clean();
    _saveFile($info, $filename, $append);
}

function d2f2($append = false, $filename = '3.html')
{
    global $html_begin, $html_end;
    ob_start();
    echo $html_begin;
    $a = debug_backtrace();
    $out = array();
    foreach ($a as $key => $val) {
        if (!isset($val['file'])) continue;
        $out[] = $val['file'] . ' [' . $val['line'] . '] ::' . $val['function'];
    }
    print_r($out);
    echo $html_end;
    $info = ob_get_contents();
    ob_end_clean();
    _saveFile($info, $filename, $append);
}

function _getProperties($obj, $level = 1, $proName = 'all', $exludes = [])
{
    $level = (int)$level;
    if ($level < 1 || $level > 3) {
        throw new Exception('暂时只支持1-3级');
    }
    $reflect = new \ReflectionObject($obj);
    //获取所有属性的值
    $props = [];
    if ($proName === 'all') {
        $props = $reflect->getProperties(ReflectionProperty::IS_PUBLIC | ReflectionProperty::IS_PROTECTED | ReflectionProperty::IS_PRIVATE | ReflectionProperty::IS_STATIC);
        //获取某个或某些属性的值

    } else {
        $targets = array($proName);
        foreach ($targets as $pr) {
            $props[] = $reflect->getProperty($pr);
        }
    }

    //排除
    if (!empty($exludes) && is_array($exludes)) {
        foreach ($props as $k => $v) {
            if (in_array($v->getName(), $exludes)) {
                unset($props[$k]);

            }
        }
    }

    $_tmp = array();
    $_sublevel = $level - 1;

    foreach ($props as $t => $pro) {
        $pro->setAccessible(true);
        $name = $pro->getName();
        $value = $pro->getValue($obj);
        if ($level == 1) {
            if (is_object($value)) $value = get_class($value) . '对象';
            if (is_array($value)) {
                foreach ($value as $k => &$v) {
                    if (is_object($v)) $v = get_class($v) . '对象';
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


/**
 * 获取调试文件的保存路径，用户根据自己的实际情况调整保存目录
 */
function _saveFile($info, $filename, $append)
{
    $saveDir = $_SERVER['DOCUMENT_ROOT'] . "/0000";
    is_dir($saveDir) || mkdir($saveDir, 0777); //如果文件夹不存在，先建文件夹
    $saveFile = $saveDir . DIRECTORY_SEPARATOR . $filename; //如果文件不存在，先新建一个再使用file_put_contents
    if (!is_file($saveFile)) {
        if (($TxtRes = fopen($saveFile, "w+")) === FALSE) {
            echo("创建可写文件：" . $filename . "失败");
            exit();
        }
    }
    $flag = ($append) ? FILE_APPEND : LOCK_EX;
    file_put_contents($saveFile, $info, $flag);
}

/*function setPublic($object,$pro=null){
    $reflect = new \ReflectionObject($object);
    if(null===$pro){
    $props = $reflect->getProperties(ReflectionProperty::IS_PUBLIC | ReflectionProperty::IS_PROTECTED | ReflectionProperty::IS_PRIVATE | ReflectionProperty::IS_STATIC);
    }else{
       if(!is_string($pro))
           throw new Exception("属性的名字为字符串");
        $props[] = $reflect->getProperty($pro);
    }

    foreach ($props as $v){
        $v->setAccessible(true);
    }
}*/

/**
 * @param $str
 * @return mixed
 * 用途:  此方法用于清理打印对象生成的空行等
 */
function _clean($str)
{
    $patterns = [];
    $patterns[0] = '/\*RECURSION\*/';
    $patterns[1] = '/\n\n/';
    $patterns[2] = '/(Array|Object)\n\s*\(/';
    $patterns[3] = '/\(\s+\)/';
    $patterns[4] = '/\[(\w+)(?:\:\w+)?\]/';  //将[docOptions:protected] [scope]等去掉[]及:protected等，第2个括号中加?:不捕获
    //$patterns[5]     = '/^[^\s].*[^\(]$/';  // ^[^\s].*[^\(]$ 去掉顶头的行但结尾不是(的
    //$patterns[5]     = '/^\s{0,1}[^\s].*[^(]$/'; //去掉第一个了符是空格，后面的不是，放在所有的之后再清
    $replacements = [];
    $replacements[0] = '';
    $replacements[1] = "\n";
    $replacements[2] = '$1(';
    $replacements[3] = '()';
    $replacements[4] = '$1';
    //$replacements[5] = '';
    $str = preg_replace($patterns, $replacements, $str);
    //$str3 = preg_replace('&gt;', '=>', $str2); // 这个在这里没起做用，只能在vs中替换

    return $str; // 20190312改
}

/**
 * @param $printr
 * @return array
 * 用途：对象转为数组
 */
function object2class($printr)
{
    $newarray = array();
    $a[0] = &$newarray;
    if (preg_match_all('/^\s+\[(\w+).*\] => (.*)\n/m', $printr, $match)) {
        foreach ($match[0] as $key => $value) {
            (int)$tabs = substr_count(substr($value, 0, strpos($value, "[")), "        ");
            if ($match[2][$key] == 'Array' || substr($match[2][$key], -6) == 'Object') {
                $a[$tabs + 1] = &$a[$tabs][$match[1][$key]];
            } else {
                $a[$tabs][$match[1][$key]] = $match[2][$key];
            }
        }
    }
    return $newarray;
}

function object2array($printr)
{
    $newarray = array();
    $a[0] = &$newarray;
    if (preg_match_all('/^\s+\[(\w+).*\] => (.*)\n/m', $printr, $match)) {
        foreach ($match[0] as $key => $value) {
            (int)$tabs = substr_count(substr($value, 0, strpos($value, "[")), "        ");
            if ($match[2][$key] == 'Array' || substr($match[2][$key], -6) == 'Object') {
                $a[$tabs + 1] = &$a[$tabs][$match[1][$key]];
            } else {
                $a[$tabs][$match[1][$key]] = $match[2][$key];
            }
        }
    }
    return $newarray;
}


function myerror($errno, $errstr, $errfile, $errline)
{
    switch ($errno) {
        case E_ERROR:
        case E_PARSE:
        case E_CORE_ERROR:
        case E_COMPILE_ERROR:
        case E_USER_ERROR:
            ob_end_clean();
            $errorStr = "$errstr " . $errfile . " 第 $errline 行.";
            p2f($errorStr, 1, '5.html');
            break;
        case E_STRICT:
        case E_USER_WARNING:
        case E_USER_NOTICE:
        default:
            // $errorStr = "[$errno] $errstr ".$errfile." 第 $errline 行.";
            break;
    }
}

function classfile($className)
{
    if (is_object($className)) {
        $className = get_class($className);
    }
    $classReflection = new ReflectionClass($className);
    //print_r($classReflection);
    return _rootalias($classReflection->getFileName());
}

// 将根目录替换为@
function _rootalias($str)
{
    return str_replace($_SERVER['DOCUMENT_ROOT'], '@', $str);
}

function _unset($key, &$arr)
{
    if (isset($arr[$key]))
        unset($arr[$key]);

}


function copydir($source, $dest)
{
    if (!file_exists($dest)) mkdir($dest);
    $handle = opendir($source);
    while (($item = readdir($handle)) !== false) {
        if ($item == '.' || $item == '..') continue;
        $_source = $source . '/' . $item;
        $_dest = $dest . '/' . $item;
        if (is_file($_source)) copy($_source, $_dest);
        if (is_dir($_source)) copydir($_source, $_dest);
    }
    closedir($handle);
}











