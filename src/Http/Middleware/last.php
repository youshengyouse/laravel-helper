<?php


namespace Yousheng\LaravelHelper\Http\Middleware;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Route;
use Help;

class Last
{
    public function handle(Request $request,$next)
    {
            return $next($request);
        /**--------------全局中间件最后一个-----------------**/
        $GlobalMiddlewareLast=<<<EOT
<h2>最后一个全局中间件_去时</h2>
EOT;
       // YOUSHENG_DEBUG && \d($GlobalMiddlewareLast,$request,app('router'));
        /**--------------/全局中间件最后一个-----------------**/
       $response = $next($request);

        /**--------------全局中间件最后一个-----------------**/
        $GlobalMiddlewareLast=<<<EOT
<h2>最后一个全局中间件_回时</h2>
EOT;
        //YOUSHENG_DEBUG && \d($GlobalMiddlewareLast,$response);
        /**--------------/全局中间件最后一个-----------------**/
       return $response;
    }
}