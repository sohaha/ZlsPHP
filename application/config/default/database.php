<?php
/**
 * 数据库配置
 * 可以直接在这里修改,也可以修改根目录的zls.ini
 */
return
    [
        //默认组,组名=>配置
        'default_group' => z::config('ini.db.group', true, 'mysql'),
        'mysql'         => [
            'driverType'               => 'mysql',
            'debug'                    => z::config('ini.db.debug', true, true),
            'trace'                    => false,
            'pconnect'                 => false,
            'charset'                  => 'utf8',
            'collate'                  => 'utf8_general_ci',
            'database'                 => z::config('ini.db.database', true, ''),
            'tablePrefix'              => z::config('ini.db.tablePrefix', true, ''),
            'tablePrefixSqlIdentifier' => '_tablePrefix_',
            'slowQueryDebug'           => false,//开启慢查询记录
            'slowQueryTime'            => 3000, //慢查询最小时间,单位毫秒，1秒=1000毫秒
            'slowQueryHandle'          => new \Zls_Database_SlowQuery_Handle_Default(),
            'indexDebug'               => false,//开启没有满足设置的索引类型的查询记录
            /**
             * 索引使用的最小情况，只有小于最小情况的时候才会记录sql到日志
             * minIndexType值从好到坏依次是:
             * system > const > eq_ref > ref > fulltext > ref_or_null
             * > index_merge > unique_subquery > index_subquery > range
             * > index > ALL 一般来说，得保证查询至少达到range级别，最好能达到ref
             * 避免ALL即全表扫描
             */
            'minIndexType'             => 'index',
            'indexHandle'              => new \Zls_Database_Index_Handle_Default(),
            'resetSql'                 => false,
            'attribute'                => [],
            'masters'                  => [
                'master01' => [
                    'hostname' => z::config('ini.db.hostname', true, ''),
                    'port'     => z::config('ini.db.port', true, 3306),
                    'username' => z::config('ini.db.username', true, ''),
                    'password' => z::config('ini.db.password', true, ''),
                ],
            ],
            'slaves'                   => [
                //'slave01' => array(
                //	'hostname' => '127.0.0.1',
                //	'port'     => 3306,
                //	'username' => 'root',
                //	'password' => '',
                //),
            ],
        ],
        'sqlsrv'        => [
            'driverType'               => 'sqlsrv',
            'debug'                    => true,
            'charset'                  => 'utf8',
            'collate'                  => 'utf8_general_ci',
            'database'                 => 'zls',
            'timeout'                  => 5,//超时时间
            'tablePrefix'              => '',
            'tablePrefixSqlIdentifier' => '_tablePrefix_',
            'slowQueryDebug'           => true,
            'slowQueryTime'            => 3000,
            'slowQueryHandle'          => new \Zls_Database_SlowQuery_Handle_Default(),
            'indexDebug'               => true,
            'minIndexType'             => 'index',
            'indexHandle'              => new \Zls_Database_Index_Handle_Default(),
            'masters'                  => [
                'master01' => [
                    'hostname' => '127.0.0.1',
                    'port'     => 1433,
                    'username' => 'sa',
                    'password' => '',
                ],
            ],
            'slaves'                   => [
                //'slave01' => array(
                //	'hostname' => '127.0.0.1',
                //	'port'     => 1433,
                //	'username' => 'sa',
                //	'password' => '',
                //),
            ],
        ],
        'sqlite3'       => [
            'driverType'               => 'sqlite',
            'debug'                    => true,
            'pconnect'                 => false,
            'tablePrefix'              => z::config('ini.db.tablePrefix', true, ''),
            'tablePrefixSqlIdentifier' => '_tablePrefix_',
            'database'                 => z::config('ini.db.database', true, ''), //sqlite3数据库路径
            'slowQueryDebug'           => true,//是否开启慢查询记录
            'slowQueryTime'            => 3000, //单位毫秒，1秒=1000毫秒
            'slowQueryHandle'          => new \Zls_Database_SlowQuery_Handle_Default(),
        ],
        //自定义
        'my_db'         => [
            'driverType'  => function () {
                $dbms = 'mysql';
                $host = z::config('ini.db.hostname', true, '');
                $dbName = z::config('ini.db.database', true, '');
                $user = z::config('ini.db.username', true, '');
                $pass = z::config('ini.db.password', true, '');
                $dsn = "{$dbms}:host={$host};dbname={$dbName}";
                return new PDO($dsn, $user, $pass);
            },
            //修改sql语句
            'resetSql'    => function ($sql) {
                return $sql;
            },
            'debug'       => true,
            'tablePrefix' => z::config('ini.db.tablePrefix', true, ''),
        ],
    ];
