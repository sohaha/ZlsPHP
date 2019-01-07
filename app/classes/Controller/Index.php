<?php
namespace Controller;
use Z;
/**
 * Zls
 * @author        影浅
 * @email         seekwe@gmail.com
 * @copyright     Copyright (c) 2015 - 2017, 影浅, Inc.
 * @link          ---
 * @since         v0.0.1
 * @updatetime    2017-05-30 21:32
 */
class Index extends \Zls_Controller
{
    public function before($method, $controllerShort, $args, $controller)
    {
    }
    public function after($contents, $methodName, $controllerShort, $args, $controller)
    {
        echo $contents;
    }
    public function zIndex()
    {
        z::debug('part');
        $data = new \DateTime();
        return [
            'time' => $data->format('Y-m-d H:i:s'),
            'clientIp' => z::clientIp(),
            'host' => z::host(true, true, true),
            'part' => z::debug('part', true, true, false),
            'global' => z::debug(null, false, true, false)
        ];
    }
    public function call()
    {
        return z::json(404);
    }
}
