<?php
namespace Artisan;
use Z;
/**
 * 本地服务器
 * @author        影浅
 * @email         seekwe@gmail.com
 * @copyright     Copyright (c) 2015 - 2017, 影浅, Inc.
 * @link          ---
 * @since         v0.0.1
 * @updatetime    2018-02-01 15:01
 */
class Start extends \Zls_Artisan
{
    /**
     * @param \Zls_CliArgs $args
     */
    function execute(\Zls_CliArgs $args)
    {
        $iniFile = z::realPath('../zls.ini');
        $exampleIniFile = z::realPath('../zls.ini.example');
        if (!file_exists($iniFile) && file_exists($exampleIniFile)) {
            @copy($exampleIniFile, $iniFile);
        }
        $port = $args->get('port', $args->get(3, 3780));
        $ip = $args->get('host', '127.0.0.1');
        $s = $ip . ':' . $port;
        $cmd = z::phpPath() . ' -S ' . $s . ' -t ' . z::realPath(ZLS_PATH);
        foreach (z::config()->getPackages() as $path) {
            if (file_exists($filePath = $path . '/classes/Other/Start.php')) {
                $cmd .= ' -file ' . $filePath;
            }
        }
        echo "HttpServe: http://{$s}" . PHP_EOL;
        try {
            echo z::command($cmd);
        } catch (\Zls_Exception_500 $e) {
            echo $e->getMessage() . PHP_EOL;
        }
    }
}
