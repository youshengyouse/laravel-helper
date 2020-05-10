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



ini_set('display_errors', 'On');
error_reporting(E_ALL);

if (!function_exists('p2f')) {
    /**
     * dump in a file,not to block application.
     * example: p2f(app('view));
     * http|s://your-domain/0000/
     */
    function p2f($item, $append = false, $file = "index.html", $maxLevel = null)
    {
        if(class_exists('Help')){
            return \Help::print2file($item, $append, $file, $maxLevel);
        }else{
            $fileSystem = new \Illuminate\Filesystem\Filesystem();
            $helper = new \Yousheng\LaravelHelper\Helper(app(),$fileSystem);
            $helper->print2file($item, $append, $file, $maxLevel);
        }
    }
}
if (!function_exists('d')) {
    /**
     * get property value,include private scope and protected scope
     * 读取对象的属性，包括私有及保护属性
     *
     * example: p2f(getprop(app('view),'all',1))
     */
    function d($obj, $append = false, $file = "index.html")
    {
        return Help::dump($obj, $append, $file);
        /*
        $fileSystem = app('files');
        $debugArray = debug_backtrace();
        $debugFile = $debugArray[0]['file'];
        $debugLine = $debugArray[0]['line'];
        $saveDir = $_SERVER['DOCUMENT_ROOT'] . "/0000";
        $fileSystem->ensureDirectoryExists($saveDir);
        $saveFile = $saveDir . DIRECTORY_SEPARATOR . $file;
        ob_start();
        \Symfony\Component\VarDumper\VarDumper::dump($item);
        $content = ob_get_clean();
        $content = "File:____" . $debugFile . '    => Line:____' . $debugLine . "\n".$content;
        if ($append) {
            $fileSystem->append($saveFile, $content);
        } else {
            $fileSystem->put($saveFile, $content);
        }
        */
    }
}


