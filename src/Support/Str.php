<?php


namespace Yousheng\LaravelHelper\Support;


use phpDocumentor\Reflection\Types\Integer;

class Str
{
    public static function randomChineseChar($num)
    {
        $num=(int)$num;
        if($num<1){
            return '有声有色';
        }
        $b = '';
        for ($i=0; $i<$num; $i++) {
            // 使用chr()函数拼接双字节汉字，前一个chr()为高位字节，后一个为低位字节
            // GB2312编码的汉字偏僻字少
            $a = chr(mt_rand(0xB0,0xD0)).chr(mt_rand(0xA1, 0xF0));
            // 转码
            $b .= iconv('GB2312', 'UTF-8', $a);
        }
        return $b;
}
}