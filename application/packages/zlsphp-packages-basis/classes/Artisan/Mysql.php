<?php
namespace Artisan;
use Z;
/**
 * mysql执行
 * @author        影浅
 * @email         seekwe@gmail.com
 * @copyright     Copyright (c) 2015 - 2017, 影浅, Inc.
 * @link          ---
 * @since         v0.0.1
 * @updatetime    2018-02-01 15:01
 */
class Mysql extends \Zls_Artisan
{
    private $dir = '../database';
    private $prefix = 'ArtisanExport_';
    public function execute(\Zls_CliArgs $args)
    {
        $method = $args->get('type', $args->get(3));
        if (method_exists($this, $method)) {
            $this->$method($args);
        } else {
            echo "Mysql Command: export|import\n";
        }
    }
    public function import(\Zls_CliArgs $args)
    {
        $filePath = $args->get('file');
        $backup = $args->get('backup', true);
        $tablePrefix = z::tap(\explode(':', $args->get('prefix', '')), function ($prefix) {
            return (count($prefix) < 2) ? false : $prefix;
        });
        $dbExist = true;
        /**
         * @var \Zls\Util\Mysql $UtilMysql
         */
        $UtilMysql = null;
        try {
            try {
                $UtilMysql = z::extension('Util\Mysql');
            } catch (\Exception $exc) {
                $errMsg = $exc->getMessage();
                z::throwIf(!preg_match('/Database Group(.*)Unknown database(.*)/', $errMsg), 'Database', $errMsg);
                //数据库找不到,新建立一个
                $dbExist = false;
                $db = z::db();
                $config = $db->getConfig();
                $database = $config['database'];
                $sql = 'CREATE DATABASE ' . $database;
                $master = z::tap($db->getMasters(), function ($master) {
                    return end($master);
                });
                try {
                    $pdo = new \Zls_PDO('mysql:host=' . z::arrayGet($master, 'hostname1') . ';port=' . z::arrayGet($master, 'port') . ';dbname=mysql;charset=' . z::arrayGet($config, 'charset'), z::arrayGet($master, 'username'), z::arrayGet($master, 'password'));
                    $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
                    $pdo->exec($sql);
                } catch (\Exception $exc) {
                    z::throwIf(true, 'Database', $sql . ' Error, Please manually create the database');
                }
                $UtilMysql = z::extension('Util\Mysql');
            }
            if ($dbExist && $backup) {
                $allTable = $UtilMysql->allTable();
                if (\count($allTable) > 0) {
                    echo 'Database exists, create a backup' . \PHP_EOL;
                    try {
                        $msg = $UtilMysql->export(null, '', 'Backup_' . $this->prefix);
                        foreach ($msg as $v) {
                            echo $v . \PHP_EOL;
                        }
                    } catch (\Exception $exc) {
                        echo $exc->getMessage() . \PHP_EOL;
                    }
                }
            }
            $date = 0;
            if (!$filePath) {
                if ($dh = opendir(z::realPathMkdir($this->dir))) {
                    while (($file = readdir($dh)) !== false) {
                        if ($file != "." && $file != ".." && preg_match('/^' . $this->prefix . '(\d+)_(.*)/', $file, $volume)) {
                            $newDate = $volume[1];
                            if ($newDate > $date) {
                                $date = $newDate;
                                $filePath = $file;
                            }
                        }
                    }
                }
            }
            $res = $UtilMysql->import(z::realPath($this->dir . '/' . $filePath), $tablePrefix);
            foreach ($res as $v) {
                echo $v . \PHP_EOL;
            }
        } catch (\Exception $exc) {
            echo $exc->getMessage() . \PHP_EOL;
        }
    }
    public function export(\Zls_CliArgs $args)
    {
        $table = $args->get('table');
        $filename = $args->get('filename');
        if ($dir = $args->get('dir')) {
            $dir = z::realPathMkdir($dir, true);
        }
        if ($ignoreData = $args->get('ignore')) {
            $_ignoreData = [];
            foreach (\explode(',', $ignoreData) as $v) {
                $v = \explode(':', $v);
                if ((int)z::arrayGet($v, 1) === 1) {
                    $_ignoreData[$v[0]] = false;
                } else {
                    $_ignoreData[$v[0]] = true;
                }
            }
            $ignoreData = $_ignoreData;
        }
        /**
         * @var \Zls\Util\Mysql $UtilMysql
         */
        $UtilMysql = z::extension('Util\Mysql');
        try {
            echo 'Start backup, please wait' . \PHP_EOL;
            $res = $UtilMysql->export($table, $dir, $this->prefix, $ignoreData, $filename);
            foreach ($res as $v) {
                echo $v . \PHP_EOL;
            }
        } catch (\Exception $exc) {
            echo $exc->getMessage() . \PHP_EOL;
        }
    }
}
