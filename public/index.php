<?php

/**
 * Zls
 * @author        影浅
 * @email         seekwe@gmail.com
 * @see           https://docs.73zls.com/zls-php/#
 */
// 关闭插件模式
defined('ZLS_RUN_MODE_PLUGIN') || define('ZLS_RUN_MODE_PLUGIN', false);
// 开启终端模式
defined('ZLS_RUN_MODE_CLI') || define('ZLS_RUN_MODE_CLI', true);
// 根目录路径
defined('ZLS_PATH') || define('ZLS_PATH', __DIR__ . '/');
// 项目目录路径
defined('ZLS_APP_PATH') || define('ZLS_APP_PATH', ZLS_PATH . '../app/');
// 入口文件名称
defined('ZLS_INDEX_NAME') || define('ZLS_INDEX_NAME', pathinfo(__FILE__, PATHINFO_BASENAME));
// 引入核心
require __DIR__ . '/../vendor/autoload.php';
// 缓存目录,请保证有写入权限
defined('ZLS_STORAGE_PATH') || define('ZLS_STORAGE_PATH', ZLS_APP_PATH . '../storage/');
Zls::initialize()
    // 设置指令
    // ->setCommands([])
    // 设置中间件
    // ->setHttpMiddleware(['\Middleware\Http'])
    // 设置错误级别
    //->setExceptionLevel(E_ALL ^ E_DEPRECATED)
    // 设置缓存
    ->setCacheConfig('cache')
    // 设置数据库连接信息
    ->setDatabaseConfig('database')
    // 设置自定义的错误显示控制处理类
    //->setExceptionHandle(new \Exception\Wx())
;
Zls::run();
