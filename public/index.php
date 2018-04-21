<?php
/**
 * Zls
 * @package       ZlsPHP
 * @author        影浅
 * @email         seekwe@gmail.com
 * @copyright     Copyright (c) 2015 - 2017, 影浅, Inc.
 * @link          ---
 * @since         v1.2.3
 * @updatetime    2017-05-30 21:16:32
 */
define('ZLS_RUN_MODE_PLUGIN', false);
define('ZLS_PATH', __DIR__. '/');
define('ZLS_APP_PATH', ZLS_PATH. '../application/');
define('ZLS_INDEX_NAME', pathinfo(__FILE__, PATHINFO_BASENAME));
require __DIR__ . '/../application/core/Zls.php';
Zls::initialize()
    //->setApplicationDir(ZLS_APP_PATH)
    //->setStorageDirPath(ZLS_APP_PATH . 'storage/')
    ->addPackages(
        [
            ZLS_PACKAGES_PATH . 'zlsphp-packages-basis',
            ZLS_PACKAGES_PATH . 'zlsphp-packages-auth',
            ZLS_PACKAGES_PATH . 'zlsphp-packages-wechat',
            ZLS_PACKAGES_PATH . 'zlsphp-packages-swoole',
        ]
    )
    //->setEnvironment(($env = (($cliEnv = \Z::getOpt('env')) ? $cliEnv : \Z::arrayGet($_SERVER, 'ENVIRONMENT'))) ? $env : 'production')
    //->setExceptionLevel(E_ALL ^ E_DEPRECATED)
    //->setShowError(Z::config()->getEnvironment() !== 'production')
    //->setTraceStatus(true)//\Z::config()->getEnvironment() != 'production'
    //->setExceptionControl(true)
    //->setApiDocToken('zls')
    ->setMethodUriSubfix('.aspx')
    //->setClientIpConditions(['HTTP_X_FORWARDED_FOR', 'HTTP_CLIENT_IP', 'REMOTE_ADDR'], ['HTTP_X_FORWARDED_FOR'])
    //->setIsRewrite(true)
    //->setSeparationRouter(true)
    //->addRouter(new \Zls\Router\Get())
    // 默认控制器
    ->setDefaultController('Zls')
    // 注册hmvc模块
    ->setHmvcModules(Z::config('hmvc'))
    ->setSessionConfig('session')
    // 设置缓存
    // ->setCacheConfig('cache')
    // 设置数据库连接信息
    ->setDatabseConfig('database')
    // 设置自定义的错误显示控制处理类
    // ->setExceptionHandle(new \Exception\Wx())
    // 错误日志记录，注释掉这行会关闭日志记录，去掉注释则开启日志文件记录,第一个参数是日志文件路径，第二个参数为是否记录404类型异常
    ->addLoggerWriter(new \Zls\Logger\FileWriter(ZLS_APP_PATH . 'storage/logs/', false, true))
    //->setLogsSubDirNameFormat('Y-m-d/H')
    //设置session托管类型 可以直接传入Zls_Session类对象,也可以传入配置文件名称，配置文件里面要返回一个Zls_Session类对象。
    //->setSessionHandle('sessionHandle')
;
return Zls::run();
