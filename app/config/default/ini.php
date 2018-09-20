<?php
/**
 * ini入口配置
 */
$iniFile = z::realPath('../zls.ini');
if (!file_exists($iniFile)) {
    $exampleIniFile = z::realPath(ZLS_APP_PATH.'../zls.ini.example');
    if (!(file_exists($exampleIniFile) && @copy($exampleIniFile, $iniFile))) {
        Z::finish('zls.ini not found , please create zls.ini.');
    }
}
return parse_ini_file($iniFile, true);
