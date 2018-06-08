<?php
/**
 * 系统通用配置
 */
return [
    'showError'    => z::config('ini.base.debug', true, false),
    'maintainMode' => z::config('ini.base.maintainMode', true, false),
    'ipWhitelist'  => z::config('ini.base.ipWhitelist', true, []),
];
