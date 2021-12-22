<?php
/**
 * Session托管配置
 */
return [];
/**
 * Memcache托管
 * tcp写法（多个Memcache可以使用英文半角逗号分隔）：
 * tcp://127.0.0.1:11211?persistent=0&timeout=3&weight=1,tcp://192.168.1.33:11211?persistent=0&timeout=3&weight=2
 * 可以使用的参数：
 * persistent:1|0 是否持久连接
 * weight:1 整数，存储权重
 * timeout:1 整数，超时时间，单位秒
 * retry_interval:15 整数，失败重试间隔单位秒
 */
/**
 * return new \Zls\Session\Memcache(['path' => 'tcp://127.0.0.1:11211?persistent=0&timeout=3']);
 */
/**
 * Memcached托管
 * 用法和Memcache一样，只是前面不带tcp://
 */
/**
 * return new \Zls\Session\Memcached(['path' => '127.0.0.1:11211?persistent=0&timeout=3']);
 */
/**
 * Redis托管
 * 套接字写法（多个redis可以使用英文半角逗号分隔）：
 * unix:///var/run/redis/redis.sock?weight=2&timeout=2.5
 * tcp写法（多个redis可以使用英文半角逗号分隔）：
 * tcp://host1:6379?weight=1&timeout=2.5,tcp://host2:6379?weight=2&timeout=2.5
 * 可以使用的参数：
 * persistent：1|0 是否持久连接
 * weight：1 整数，存储权重
 * timeout：3.0 浮点数,超时时间，单位秒
 * prefix：PHPREDIS_SESSION session键名称的前缀
 * auth：字符串，认证密码，避免特殊字符影响可以需要使用urlencode()处理一下
 * database：0 整数，数据库号
 */
/**
 * return new \Zls\Session\Redis(['path' => 'tcp://127.0.0.1:6379?timeout=3&persistent=0']);
 */
/**
 * Mongodb托管
 */
/*return new \Zls\Session\Mongodb([
        'host'         => '127.0.0.1', //mongodb主机地址
        'port'         => 27017, //端口
        'user'         => 'root',
        'password'     => '',
        'database'     => 'local', //   MongoDB 数据库名称
        'collection'   => 'sessions', //   MongoDB collection名称
        'persistent'   => false, // 是否持久连接
        'persistentId' => 'ZlsMongoSession', // 持久连接id
        // 是否支持 replicaSet
        'replicaSet'   => false,
    ]
);*/
/**
 * MySQL托管
 * 表结构如下：
 * CREATE TABLE `session_handler_table` (
 * `id` varchar(255) NOT NULL,
 * `data` mediumtext NOT NULL,
 * `timestamp` int(255) NOT NULL,
 * PRIMARY KEY (`id`),
 * UNIQUE KEY `id` (`id`,`timestamp`),
 * KEY `timestamp` (`timestamp`)
 * ) ENGINE=InnoDB DEFAULT CHARSET=utf8;
 */
/*return new \Zls\Session\Mysql([
        //如果使用数据库配置里面的组信息，这里可以设置group组名称，没有就留空
        //设置group组名称后，下面连接的配置不再起作用，group优先级大于下面的连接信息
        'group'        => '',
        //表全名，不包含前缀
        'table'        => 'session_handler_table',
        //表前缀，如果有使用数据库配置组里面的信息
        //这里可以设置相同的数据库配置组里面的前缀才能正常工作
        'table_prefix' => '',
        //连接信息
        'hostname'     => '127.0.0.1',
        'port'         => 3306,
        'username'     => 'root',
        'password'     => 'admin',
        'database'     => 'test',
    ]
);*/
