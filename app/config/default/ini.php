<?php
/**
 * ini入口配置
 */
$iniFile = Z::realPath('../zls.ini');
if (!file_exists($iniFile)) {
    $exampleIniFile = Z::realPath('../zls.ini.example');
    if (!(file_exists($exampleIniFile) && @copy($exampleIniFile, $iniFile))) {
        Z::end('zls.ini not found , please create zls.ini.');
    }
}
return parse_ini_file($iniFile, true);
