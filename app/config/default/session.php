<?php
/**
 * 设置session信息
 */
return [
    'autostart'         => false,//自动开启session
    'cookie_path'       => '/',
    'cookie_domain'     => Z::arrayGet(explode(':', Z::server('HTTP_HOST')), 0),
    'session_name'      => 'ZLS',
    'lifetime'          => 3600,
    'session_save_path' => null,//z::config()->getStorageDirPath().'/sessions'
];
