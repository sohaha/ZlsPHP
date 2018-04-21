<?php
namespace Zls\Cache;
/**
 * Zls_Cache_RedisCluster
 * @author      影浅-Seekwe
 * @email       seekwe@gmail.com
 * Date:        17/2/3
 * Time:        19:44
 */
//'redis_cluster' => array(
//    'class'  => 'Zls_Cache_RedisCluster',
//    'config' => array(
//        'hosts'        => array(//集群中所有master主机信息
//                                //'127.0.0.1:7001',
//                                //'127.0.0.1:7002',
//                                //'127.0.0.1:7003',
//        ),
//        'timeout'      => 1.5,//连接超时，单位秒
//        'read_timeout' => 1.5,//读超时，单位秒
//        'persistent'   => false,//是否持久化连接
//        //key的前缀，便于管理查看，在set和get的时候会自动加上和去除前缀，无前缀请保持null
//        'prefix'       => null, //Z::server('HTTP_HOST')
//    ),
//),
use Z;
class RedisCluster implements \Zls_Cache
{
    private $config, $handle;
    public function __construct($config)
    {
        if (!is_null($config['prefix']) && ($config['prefix']{strlen($config['prefix']) - 1} != ':')) {
            $config['prefix'] .= ':';
        }
        $this->config = $config;
    }
    public function reset()
    {
        $this->handle = null;
        return $this;
    }
    public function clean()
    {
        throw new \Zls_Exception_500('clean method not supported of \Zls\Cache\Redis\Cluster ');
    }
    public function delete($key)
    {
        $this->_init();
        return $this->handle->del($key);
    }
    private function _init()
    {
        if (empty($this->handle)) {
            $this->handle = new \RedisCluster(null, $this->config['hosts'], $this->config['timeout'],
                $this->config['read_timeout'], $this->config['persistent']);
            if ($this->config['prefix']) {
                $this->handle->setOption(RedisCluster::OPT_PREFIX, $this->config['prefix']);
            }
        }
    }
    public function get($key)
    {
        $this->_init();
        if ($rawData = $this->handle->get($key)) {
            $data = @unserialize($rawData);
            return $data ? $data : $rawData;
        } else {
            return null;
        }
    }
    public function set($key, $value, $cacheTime = 0)
    {
        $this->_init();
        $value = serialize($value);
        if ($cacheTime) {
            return $this->handle->setex($key, $cacheTime, $value);
        } else {
            return $this->handle->set($key, $value);
        }
    }
    public function &instance($key = null, $isRead = true)
    {
        $this->_init();
        return $this->handle;
    }
}
