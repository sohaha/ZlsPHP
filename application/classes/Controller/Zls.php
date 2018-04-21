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
class Zls extends \Zls_Controller
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
        //\sleep(1);
        return z::json(200, 'ok', [
            '运行成功',
            z::clientIp(),
            z::host(true, true, true),
            z::debug('index', true, true),
            z::debug(),
        ]);
    }
}
