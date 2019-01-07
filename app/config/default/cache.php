<?php
/**
 * 缓存配置
 */
return [
    'default_type' => z::config('ini.cache.type', true, 'file'), //默认的缓存类型，值是下面drivers关联数组的键名称。
    'drivers'      => [
        //自定义缓存示例
        //'my_cache'      => [
        //    'class'  => '\Cache_MyCache', //缓存类名称
        //    'config' => null//需要传递给缓存类构造方法的第一个参数，一般是配置信息数组，不需要就保持null
        //],
        'file'          => [
            //缓存文件保存路径
            'config' => \Z::config()->getStorageDirPath() . 'cache/',
        ],
        'memcache'      => [
            'class'  => '\Zls\Cache\Memcache',
            'config' => [
                ["127.0.0.1", 11211],
            ],
        ],
        'memcached'     => [
            'class'  => '\Zls\Cache\Memcached',
            'config' => [
                ["127.0.0.1", 11211],
            ],
        ],
        'apc'           => [
            'class'  => '\Zls\Cache\Apc',
            'config' => null//apc缓存不需要配置信息，保持null即可
        ],
        'redis_cluster' => [
            'class'  => '\Zls\Cache\RedisCluster',
            'config' => [
                'hosts'        => [//集群中所有master主机信息
                                   //'127.0.0.1:7001',
                                   //'127.0.0.1:7002',
                                   //'127.0.0.1:7003',
                ],
                'timeout'      => 1.5,//连接超时，单位秒
                'read_timeout' => 1.5,//读超时，单位秒
                'persistent'   => false,//是否持久化连接
                'prefix'       => null, //Zls::server('HTTP_HOST')
            ],
        ],
        'redis'         => [
            'class'  => '\Zls\Cache\Redis',
            'config' =>
                [
                    //原理是：读写的时候根据算法sprintf('%u',crc32($key))%count($nodeCount)
                    //把$key分散到下面不同的master服务器上，负载均衡，而且还支持单个key的主从负载均衡。
                    [
                        'master' => [
                            'type'     => 'tcp',
                            'prefix'   => null, //Zls::server('HTTP_HOST')
                            'sock'     => '',
                            //主机地址
                            'host'     => '127.0.0.1',
                            //端口
                            'port'     => 6379,
                            //密码，如果没有,保持null
                            'password' => null,
                            'timeout'  => 3000,
                            //连接失败后的重试时间间隔，单位毫秒
                            'retry'    => 100,
                            // 数据库序号，默认0, 参考 http://redis.io/commands/select
                            'db'       => 0,
                        ],
                        'slaves' => [
                            //[
                            //    'type'     => 'tcp',
                            //    'prefix'   => null, //Zls::server('HTTP_HOST')
                            //    'sock'     => '',
                            //    'host'     => '127.0.0.1',
                            //    'port'     => 6380,
                            //    'password' => null,
                            //    'timeout'  => 3000,
                            //    'retry'    => 100,
                            //    'db'       => 0,
                            //],
                        ],
                    ],
                    //[
                    //    'master' => [
                    //        'type'     => 'tcp',
                    //        'prefix'   => null,
                    //        'sock'     => '',
                    //        'host'     => '10.69.112.34',
                    //        'port'     => 6379,
                    //        'password' => null,
                    //        'timeout'  => 3000,
                    //        'retry'    => 100,
                    //        'db'       => 0,
                    //    ],
                    //    'slaves' => [],
                    //],
                ],
        ],
    ],
];
