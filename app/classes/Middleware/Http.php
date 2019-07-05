<?php
namespace Middleware;
use Z;
/**
 * z
 * @author        影浅 <seekwe@gmail.com>
 */
class Http extends \Zls_Middleware
{
    public function handle($request, callable $next)
    {
        return $next($request);
    }
    /**
     * @return array
     */
    public function classes($request)
    {
        return [];
    }
}
