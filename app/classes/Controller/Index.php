<?php
namespace Controller;
use Z;
/**
 * Zls
 * @author        影浅 <seekwe@gmail.com>
 */
class Index extends \Zls_Controller
{
    public function before($method, $controllerShort, $args, $controller)
    {
    }
    public function after($contents, $methodName, $controllerShort, $args, $controller)
    {
        return $contents;
    }
    public function zIndex()
    {
        z::debug('part');
        $data = new \DateTime();
        return [
            'time'     => $data->format('Y-m-d H:i:s'),
            'clientIp' => z::clientIp(),
            'host'     => z::host(true, true, true),
            'part'     => z::debug('part', true, true, false),
            'global'   => z::debug(null, false, true, false),
        ];
    }
    public function call()
    {
        return z::json(404);
    }
}
