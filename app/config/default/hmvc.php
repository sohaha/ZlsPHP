<?php
/**
 * hmvc模块配置
 */
$hmvc = z::cacheDate('__hmvc__', function () {
    $hmvc = [];
    $config = z::config();
    $path = $config->getAppDir() . $config->getHmvcDirName() . '/';
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
