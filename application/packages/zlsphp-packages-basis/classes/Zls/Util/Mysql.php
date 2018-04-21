<?php
namespace Zls\Util;
use Z;
/**
 * mysql导出导入
 * @author        影浅
 * @email         seekwe@gmail.com
 * @copyright     Copyright (c) 2015 - 2017, 影浅, Inc.
 * @link          ---
 * @since         v0.0.1
 * @updatetime    2018-03-02 15:58
 */
class Mysql
{
    //换行符
    public $sqlContent = "";
    //存储SQL的变量
    public $sqlEnd = ";";
    //每条sql语句的结尾符
    private $ds = "\n";
    private $db;
    private $database;
    private $version;
    private $host;
    private $config;
    private $sqldir;
    private $master;
    public function __construct($group = '')
    {
        $this->db = z::db($group, true);
        $this->config = $config = $this->db->getConfig();
        $this->database = z::arrayGet($this->config, 'database');
        $this->master = $master = z::tap($this->db->getMasters(), function ($master) {
            return end($master);
        });
        $this->host = z::arrayGet($master, 'hostname');
        $this->version = $this->db->execute("select VERSION() as version")->value('version');
        $this->db->pod()->setAttribute(\PDO::ATTR_ORACLE_NULLS, \PDO::NULL_NATURAL);
    }
    public function export($tablename = '', $dir = '', $prefix = '', $ignoreData = [], $name = '', $size = 2000)
    {
        if ($dir !== false) {
            $dir = $dir ? $dir : z::realPathMkdir('../database', true);
            if (!z::strEndsWith($dir, '/')) {
                $dir .= '/';
            }
        }
        $msg = [];
        $sql = '';
        $db = $this->db;
        $sql .= $this->retrieve();
        $p = 1;
        $thanSize = function (&$_sql, &$p, $filename) use ($size, $dir, &$msg) {
            if (strlen($_sql) >= $size * 1000) {
                $file = $filename . "_v" . $p . ".sql";
                $res = $this->writeFile($_sql, $file, $dir);
                if ($res === true) {
                    $msg[] = "volume-" . $p . " backup completed,Generate a backup file{$dir}{$file}";
                } else {
                    z::throwIf(true, 'Exception', "volume-" . $p . " backup error:" . $res);
                }
                $p++;
                $_sql = "";
            }
        };
        $tablePrefix = $this->config['tablePrefix'];
        if (!empty ($tablename)) {
            $tables = z::arrayMap(\explode(',', $tablename), function ($name) use ($tablePrefix) {
                return ['Name' => $tablePrefix . $name];
            });
            $filename = $prefix . date('YmdHis') . "_{$tablename}";
        } else {
            $filename = $prefix . date('YmdHis') . "_all";
            //查出所有表
            $tables = $this->allTable();
            z::throwIf(!$tables, 'Exception', "database {$this->database} read failed");
        }
        if ($name) {
            $filename = $name;
        }
        foreach ($tables as $table) {
            $tablename = $table['Name'];
            $isIgnore = z::arrayGet($ignoreData, \str_replace($tablePrefix, '', $tablename), null);
            if (\is_null($isIgnore) && \is_array($ignoreData)) {
                foreach ($ignoreData as $v) {
                    if ($tablePrefix . $v === $tablename) {
                        $isIgnore = false;
                        break;
                    }
                }
            }
            $isIgnoreTable = $isIgnore === true;
            $isIgnoreData = $isIgnore === false;
            if ($isIgnoreTable || (!!$tablePrefix && !z::strBeginsWith($table['Name'], $tablePrefix))) {
                continue;
            }
            $sql .= $this->insertTableStructure($tablename, $isIgnoreData);
            if (!$isIgnoreData) {
                $total = $this->db->select('count(*) as total')->from($tablename)->execute()->value('total');
                $pagesize = 200;
                $pages = Z::page($total, 1, $pagesize, '{page}');
                for ($page = 1; $page <= $pages['count']; $page++) {
                    $items = $this->db->select('*')->limit(($page - 1) * $pagesize, $pagesize)->from($tablename)->execute()->rows();
                    foreach ($items as $k => $record) {
                        $sql .= $this->insertRecord($tablename, $k, $record);
                    }
                }
            }
            if ($dir) {
                $thanSize($sql, $p, $filename);
            }
        }
        if ($dir && $sql != "") {
            $filename .= "_v" . $p . ".sql";
            $res = $this->writeFile($sql, $filename, $dir);
            if ($res === true) {
                $msg[] ="volume-" . $p . " backup completed,Generate a backup file{$dir}{$filename}";
            } else {
                z::throwIf(true, 'Exception', "volume-" . $p . " backup error:" . $res);
            }
        }
        return ($dir) ? $msg : $sql;
    }
    /**
     * 插入数据库备份基础信息
     * @return string
     */
    private function retrieve()
    {
        $value = '';
        $value .= '--' . $this->ds;
        $value .= '-- MySQL database dump' . $this->ds;
        $value .= '-- Created by Zls\Util\Mysql class, Power By ZlsPHP. ' . $this->ds;
        $value .= '-- https://docs.73zls.com/zls-php/#/ ' . $this->ds;
        $value .= '--' . $this->ds;
        $value .= '-- 主机: ' . $this->host . $this->ds;
        $value .= '-- 生成日期: ' . date('Y') . ' 年  ' . date('m') . ' 月 ' . date('d') . ' 日 ' . date('H:i') . $this->ds;
        $value .= '-- MySQL版本: ' . $this->version . $this->ds;
        $value .= '-- PHP 版本: ' . phpversion() . $this->ds;
        $value .= $this->ds;
        $value .= '--' . $this->ds;
        $value .= '-- 数据库: `' . $this->database . '`' . $this->ds;
        $value .= '--' . $this->ds . $this->ds;
        $value .= '-- -------------------------------------------------------';
        $value .= $this->ds . $this->ds;
        return $value;
    }
    /**
     * 写入文件
     * @param string $sql
     * @param string $filename
     * @param string $dir
     * @return boolean
     */
    private function writeFile($sql, $filename, $dir)
    {
        $re = true;
        if (!@$fp = fopen($dir . $filename, "w+")) {
            $re = "fail to open the file";
        }
        if (!@fwrite($fp, $sql)) {
            $re = "Failed to write file, please file is writable";
        }
        if (!@fclose($fp)) {
            $re = "Failed to close file";
        }
        return $re;
    }
    public function allTable()
    {
        return $this->db->execute("show table status from " . $this->database)->rows();
    }
    /**
     * 插入表结构
     * @param string $table
     * @param bool   $autoIncrement
     * @return string
     */
    private function insertTableStructure($table, $autoIncrement = false)
    {
        $sql = '';
        $sql .= "--" . $this->ds;
        $sql .= "-- 表的结构" . $table . $this->ds;
        $sql .= "--" . $this->ds . $this->ds;
        $sql .= 'DROP TABLE IF EXISTS `' . $table . '`' . $this->sqlEnd . $this->ds;
        $row = $this->db->execute('SHOW CREATE TABLE `' . $table . '`')->row();
        $sql .= ($autoIncrement) ? \preg_replace('/AUTO_INCREMENT=\d+/i', 'AUTO_INCREMENT=1', $row ['Create Table']) : $row ['Create Table'];
        $sql .= $this->sqlEnd . $this->ds;
        $sql .= $this->ds;
        $sql .= "--" . $this->ds;
        $sql .= "-- 转存表中的数据 " . $table . $this->ds;
        $sql .= "--" . $this->ds;
        $sql .= $this->ds;
        return $sql;
    }
    /**
     * 插入单条记录
     * @param string $table
     * @param int    $i
     * @param array  $record
     * @return string
     */
    private function insertRecord($table, $i, $record)
    {
        $comma = "";
        $insert = "INSERT INTO `" . $table . "` VALUES(";
        foreach ($record as $k => $v) {
            $value = $v;
            if ($value !== null) {
                $value = str_replace("\n", '\n', $v);
                $value = str_replace("\t", '\t', $value);
                $value = str_replace("\r", '\r', $value);
                $insert .= ($comma . "'" . $value . "'");
            } else {
                $insert .= ($comma . "null");
            }
            $comma = ",";
        }
        $insert .= ");" . $this->ds;
        return $insert;
    }
    /**
     * 导入备份数据
     * 说明：分卷文件格式xxxx_all_v1.sql
     * 参数：文件路径(必填)
     * @param string     $sqlfile
     * @param array|null $tablePrefix
     * @return array
     */
    function import($sqlfile, $tablePrefix = null)
    {
        z::throwIf(!file_exists($sqlfile), 500, 'Database backup does not exist! Please check');
        $sqlpath = pathinfo($sqlfile);
        $this->sqldir = $sqlpath ['dirname'];
        $msg = [];
        $volume = explode("_v", $sqlfile);
        $volume_path = $volume [0];
        $msg[] = "Import backup data";
        if (empty ($volume [1])) {
            $msg[] = 'Import sql：' . $sqlfile;
            z::throwIf(!$this->_import($sqlfile, $tablePrefix), 'Exception', 'Database import failed');
            $msg[] = "Database import successful";
        } else {
            $volume_id = explode(".sq", $volume [1]);
            $volume_id = intval($volume_id [0]);
            while ($volume_id) {
                $tmpfile = $volume_path . "_v" . $volume_id . ".sql";
                if (file_exists($tmpfile)) {
                    $msg[] = "Importing sub-volumes{$volume_id}:{$tmpfile}";
                    z::throwIf(!$this->_import($tmpfile, $tablePrefix), 'Exception', "Import volumes{$volume_id}：" . $tmpfile . ' error! It may be that the database structure is damaged! , Please try to import from volume 1');
                } else {
                    $msg[] = "This backup of all volumes was successfully imported";
                    break;
                }
                $volume_id++;
            }
        }
        return $msg;
    }
    /**
     * 将sql导入到数据库（普通导入）
     * @param string $sqlfile
     * @param string $tablePrefix
     * @return boolean
     */
    private function _import($sqlfile, $tablePrefix = null)
    {
        $sqls = [];
        $f = fopen($sqlfile, "rb");
        $i = 0;
        $create = '';
        while (!feof($f)) {
            $i++;
            $line = fgets($f);
            if (trim($line) == '' || preg_match('/--(.?)/', $line, $match)) {
                continue;
            }
            if (!preg_match('/;/', $line, $match) || preg_match('/ENGINE=/', $line, $match)) {
                $create .= $line;
                if (preg_match('/ENGINE=/', $create, $match)) {
                    $sqls [] = $create;
                    $create = '';
                }
                continue;
            }
            $sqls [] = $line;
        }
        fclose($f);
        foreach ($sqls as $sql) {
            if (!!$tablePrefix) {
                $sql = str_replace("INSERT INTO `{$tablePrefix[0]}", "INSERT INTO `{$tablePrefix[1]}", $sql);
                $sql = str_replace("CREATE TABLE `{$tablePrefix[0]}", "CREATE TABLE `{$tablePrefix[1]}", $sql);
                $sql = str_replace("DROP TABLE IF EXISTS `{$tablePrefix[0]}", "DROP TABLE IF EXISTS `{$tablePrefix[1]}", $sql);
            }
            if (!$this->db->execute(trim($sql))) {
                return false;
            }
        }
        return true;
    }
    private function lock($tablename, $op = "WRITE")
    {
        if ($this->db->execute("lock tables " . $tablename . " " . $op)) {
            return true;
        } else {
            return false;
        }
    }
    private function unlock()
    {
        if ($this->db->execute("unlock tables")) {
            return true;
        } else {
            return false;
        }
    }
}
