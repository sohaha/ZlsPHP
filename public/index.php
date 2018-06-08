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
//关闭插件模式
define('ZLS_RUN_MODE_PLUGIN', false);
//根目录路径
define('ZLS_PATH', __DIR__ . '/');
//项目目录路径
define('ZLS_APP_PATH', ZLS_PATH . '../application/');
//入口文件名称
define('ZLS_INDEX_NAME', pathinfo(__FILE__, PATHINFO_BASENAME));
//引入核心
require __DIR__ . '/../application/core/Zls.php';
Zls::initialize()
    //设置缓存目录,请保证有写入权限
    //->setStorageDirPath(ZLS_APP_PATH . 'storage/')
    //设置指令
    ->setCommands([])
    //设置运行环境
    //->setEnvironment(($env = (($cliEnv = \Z::getOpt('env')) ? $cliEnv : \Z::arrayGet($_SERVER, 'ENVIRONMENT'))) ? $env : 'production')
    //设置错误级别,也就是error_reporting()的参数,只有此级别的错误才会触发下面的错误显示控制处理类
    //->setExceptionLevel(E_ALL ^ E_DEPRECATED)
    //开启调试日志
    ->setTraceStatus(true)//\Z::config()->getEnvironment() != 'production'
    //日志处理
    //->setTraceStatusCallBack(function ($log,$type){})
    //设置自动Api文档访问toekn
    //->setApiDocToken('zls')
    //设置URL后缀
    ->setMethodUriSubfix('.aspx')
    //维护模式处理方法
    ->setMaintainModeHandle(new \Zls_Maintain_Handle_Default())
    //->setClientIpConditions(['HTTP_X_FORWARDED_FOR', 'HTTP_CLIENT_IP', 'REMOTE_ADDR'], ['HTTP_X_FORWARDED_FOR'])
    //前后分离路由器
    //->setSeparationRouter(true)
    //注册路由器
    //->addRouter()
    //默认控制器
    ->setDefaultController('Zls')
    //注册hmvc模块
    ->setHmvcModules(Z::config('hmvc'))
    //设置别名
    //->setAlias([ 'Http' => \Zls\Action\Http::class, ])
    //设置缓存
    //->setCacheConfig('cache')
    //设置数据库连接信息
    ->setDatabaseConfig('database')
    //设置自定义的错误显示控制处理类
    //->setExceptionHandle(new \Exception\Wx())
    //错误日志记录，注释掉这行会关闭日志记录，去掉注释则开启日志文件记录,第一个参数是日志文件路径，第二个参数为是否记录404类型异常
    ->addLoggerWriter(new \Zls\Logger\FileWriter(ZLS_APP_PATH . 'storage/logs/', false, true))
    //设置日志记录子目录格式，参数就是date()函数的第一个参数,默认是 Y-m-d/H */
    //->setLogsSubDirNameFormat('Y-m-d/H')
    //设置session信息
    ->setSessionConfig('session')
    //设置session托管类型 可以直接传入Zls_Session类对象,也可以传入配置文件名称，配置文件里面要返回一个Zls_Session类对象。
    //->setSessionHandle('sessionHandle')
;
Zls::run();
