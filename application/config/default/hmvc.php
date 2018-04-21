<?php
/**
 * Zls
 * @author        影浅
 * @email         seekwe@gmail.com
 * @copyright     Copyright (c) 2015 - 2017, 影浅, Inc.
 * @link          ---
 * @since         v0.0.1
 * @updatetime    2017-07-26 14:33
 */
$hmvc = z::cacheDate('__hmvc__', function () {
    $hmvc = [];
    $config = z::config();
    $path = $config->getApplicationDir() . $config->getHmvcDirName() . '/';
    if (file_exists($path) && $dh = opendir($path)) {
        while (($file = readdir($dh)) !== false) {
            if ($file != "." && $file != "..") {
                $hmvc[z::strCamel2Snake($file)] = $file;
                $hmvc[$file] = $file;
            }
        }
    }
    return $hmvc;
}, 120);
return $hmvc;
