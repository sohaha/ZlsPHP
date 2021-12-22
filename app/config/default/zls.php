<?php

/**
 * 系统通用配置
 */
$base = Z::strCamel2Snake(Z::config('ini.base', true, [
    'debug' => false,
    'maintain_mode' => false,
    'ip_whitelist' => [],
]));

return [
    'showError' => $base['debug'],
    'maintainMode' => $base['maintain_mode'],
    'ipWhitelist' => $base['ip_whitelist'],
];
