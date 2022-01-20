<?php
/**
 * ----------------------------------------------------------------------
 * 调试助手.开发过程中使用。不要出现在上线产品中. A Debug helper                                                                                 |
 * ----------------------------------------------------------------------
 * Copyright (c) 2017 深圳有声有色网络科技有限公司
 * ----------------------------------------------------------------------
 * Author: daqi <2922800186@qq.com>
 * ----------------------------------------------------------------------
 */
use Yousheng\LaravelHelper\Facades\Help;


ini_set('display_errors', 'On');
error_reporting(E_ALL);

if (!function_exists('p2f')) {
    /**
     * dump in a file,not to block application.
     * example: p2f(app('view'));
     * http|s://your-domain/0000/
     */
    function p2f($item, $append = false, $dirname="0000",$file = "index.html", $maxLevel = null)
    {
        if(class_exists('Help')){
            return Help::print2file($item, $append, $dirname,$file, $maxLevel);
        }else{
            $fileSystem = new \Illuminate\Filesystem\Filesystem();
            $helper = new \Yousheng\LaravelHelper\Helper(app(),$fileSystem);
            $helper->print2file($item, $append, $dirname,$file, $maxLevel);
        }
    }
}
if (!function_exists('d')) {
    /**
     * 该函数主要用于代码的跟踪调试
     *
     * example: d("A001_应用实例化"))
     * example: d("B001_遍历服务提供商，并注册服务提供商"，config())
     */
    function d($flag,$obj=null,$showline=true, $append = FILE_APPEND, $dir="00000000",$file = "index.html")
    {
        $debugArray = debug_backtrace();
        $debugFile = $debugArray[0]['file'];
        $debugLine = $debugArray[0]['line'];

        $saveDir = $_SERVER['DOCUMENT_ROOT'] . "/${dir}";
        if(!is_dir($saveDir)){
            @mkdir(0755);
        }
        $saveFile = $saveDir . DIRECTORY_SEPARATOR . $file;
        ob_start();
        echo "<div style='background-color:#eee;pading:5px;margin-top: 15px;'>";
        if(is_object($showline) || $showline==true ){
            echo "文件: " . $debugFile . "</br>\n\r  行数: " . $debugLine;
        }
        echo "</div>";
        if(is_object($flag)){
            \Symfony\Component\VarDumper\VarDumper::dump($flag);
        }else{
            print_r($flag);
        }
        if($obj){
            \Symfony\Component\VarDumper\VarDumper::dump($obj);
        }
        if(is_object($showline)){
            \Symfony\Component\VarDumper\VarDumper::dump($showline);
        }
        $content = ob_get_clean();
        file_put_contents($saveFile, $content, $append);
    }
}

function d2f($append=false,$offset=0,$limit=null,$filename = '2.html'){
    $filteredBacktraces = [];
    $maxPathLength=0;
    $str = '';
    foreach (debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, $limit) as $dumpIndex => $backtrace) {
        if ($dumpIndex > $offset) {
            $filteredBacktrace = [
                'file' => isset($backtrace['file']) ? $backtrace['file'] : null,
                'line' => isset($backtrace['line']) ? $backtrace['line'] : null,
            ];
            $length=strlen($filteredBacktrace['file']);
            $maxPathLength=($length>$maxPathLength)?$length:$maxPathLength;
            if (isset($backtrace['class'])) {
                $filteredBacktrace['call'] =
                    $backtrace['class'] . $backtrace['type'] . $backtrace['function'] . '()';
            } elseif (isset($backtrace['function'])) {
                $filteredBacktrace['call'] = $backtrace['function'] . '()';
            } else {
                $filteredBacktrace['call'] = '\Closure';
            }
            $filteredBacktraces[] = $filteredBacktrace;

        }
    }

    // 以字符串的方式显示
    $out = [];
    foreach ($filteredBacktraces as $var){
        $str = str_pad($var['file'], $maxPathLength , "_");
        $str.=str_pad($var['line'], 4 , "_", STR_PAD_RIGHT);
        $str.='____'.$var['call'];
        $out[]=$str;
        $str='';
    }
    //return Help::dump($filteredBacktraces, false, $filename);
   return Help::dump($out, $append, $filename);
}






/**
 * @param bool $append
 * @param string $path
 * 用途： 打印函数的调用回溯信息
 */
function d2f2($short = false, $append = false, $filename = '2.html', $level = null)
{
    ini_set('max_execution_time','100');
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
            if(isset($val['file'])){

            $shortArray[$key] = _rootalias($val['file']) . '____' . $val['line'] . "行";
            }
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
                        $val['file_class']  = classfile($val['class']);
                        if($val['object'] instanceof Closure){
                            //$_object            = clone $val['object'];
                            $_object            =  '闭包';
                        }else{

                        //$_object = unserialize(serialize($val['object']));
                        $_object = $val['object'];
                        }
                        $val['object']      = get_class($_object);
                        $val['class']       .= '中定义方法____' . $val['function'] . "()";


                    } else {
                        //$val['b'] = '类'.get_class($val['object']).'中的方法'.$val['function']." ，定义类的文件是：".classfile(get_class($val['object']));
                        $val['class_file'] = classfile($val['class']);
                        if($val['object'] instanceof Closure){
                           // $_object            = clone $val['object'];
                            $_object            =  '闭包';
                        }else{

                            //$_object = unserialize(serialize($val['object']));
                            $_object = $val['object'];
                        }
                        $val['object']     = get_class($_object) . '中定义方法____' . $val['function'] . "()";
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

function _rootalias($str)
{
    return str_replace($_SERVER['DOCUMENT_ROOT'], '@', $str);
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
function _unset($key, &$arr)
{
    if (isset($arr[$key]))
        unset($arr[$key]);

}
