<?php

/**
 * 系统通用配置
 */
return [
    'showError'    => Z::config('ini.base.debug', true, false),
    'maintainMode' => Z::config('ini.base.maintainMode', true, false),
    'ipWhitelist'  => Z::config('ini.base.ipWhitelist', true, []),
];
