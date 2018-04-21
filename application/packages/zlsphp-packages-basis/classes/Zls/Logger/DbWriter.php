<?php
namespace Zls\Logger;
/**
 * 错误日志记录类
 * 把错误日志记录到数据库
 * 表结构如下：
 * CREATE TABLE  `system_error_logger` (
 * `error_logger_id` int(11) NOT NULL AUTO_INCREMENT COMMENT '主键',
 * `domain` VARCHAR( 100 ) NOT NULL COMMENT  '域名',
 * `client_ip` VARCHAR( 15 ) NOT NULL COMMENT  '访问者IP',
 * `server_ip` VARCHAR( 15 ) NOT NULL COMMENT  '服务器IP',
 * `message` TEXT NOT NULL COMMENT  '错误信息',
 * `file` VARCHAR( 1000 ) NOT NULL COMMENT  '出错文件路径',
 * `line` INT NOT NULL COMMENT  '出错行数',
 * `code` INT NOT NULL COMMENT  '出错代码',
 * `type` VARCHAR( 50 ) NOT NULL COMMENT  '错误类型',
 * `request_data` TEXT NOT NULL COMMENT  '请求的数据',
 * `create_time` INT NOT NULL COMMENT  '创建时间',
 * PRIMARY KEY (  `error_logger_id` )
 * ) ENGINE = INNODB CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci COMMENT =  '系统错误日志表'
 */
use Z;
class DbWriter implements \Zls_Logger
{
    private $table, $db, $log404;
    public function __construct($table = 'system_error_logger', $log404 = false, $dbGroup = null)
    {
        $this->db = empty($dbGroup) ? Z::db() : Z::db($dbGroup);
        $this->table = empty($table) ? 'system_error_logger' : $table;
        $this->log404 = $log404;
    }
    public function write(\Zls_Exception $exception)
    {
        if (($exception instanceof \Zls_Exception_404) && !$this->log404) {
            return;
        }
        $row['domain'] = Z::server('http_host', '');
        $row['client_ip'] = Z::clientIp();
        $row['server_ip'] = Z::serverIp();
        $row['message'] = $exception->getErrorMessage();
        $row['file'] = $exception->getErrorFile();
        $row['line'] = $exception->getErrorLine();
        $row['code'] = $exception->getErrorCode();
        $row['type'] = $exception->getErrorType();
        $row['request_data'] = json_encode([
            'get'      => Z::get(),
            'post'     => Z::post(),
            'server'   => Z::server(),
            'cookie'   => Z::cookie(),
            'session'  => Z::session(),
            'post_raw' => Z::postRaw(),
        ]);
        $row['create_time'] = time();
        $this->db->insert($this->table, $row)->execute();
    }
}
