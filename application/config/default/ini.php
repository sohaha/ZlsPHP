<?php
/**
 * Zls
 * @author        影浅
 * @email         seekwe@gmail.com
 * @copyright     Copyright (c) 2015 - 2017, 影浅, Inc.
 * @link          ---
 * @since         v0.0.1
 * @updatetime    2017-09-06 01:19
 */
if (!file_exists($path = ZLS_APP_PATH . "../zls.ini")) {
    throw new \Zls_Exception_500('zls.ini not found , please copy the zls.ini.example of the root directory and rename it to zls.ini');
}
$iniConfig = parse_ini_file($path, true);
if (z::arrayGet($iniConfig, 'base.debug')) {
    Z::config()->setShowError(true);
}
return parse_ini_file($path, true);
