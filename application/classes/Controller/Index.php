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
    public function z_index()
    {
        z::debug('index');
        $data = new \DateTime();
        return z::json(200, 'Index', [
            $data->format('Y-m-d H:i:s u'),
            z::clientIp(),
            z::host(true, true, true),
            z::debug('index', true, true),
            z::debug(),
        ]);
    }
}
