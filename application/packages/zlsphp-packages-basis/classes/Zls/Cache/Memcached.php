<?php
namespace Zls\Cache;
/**
 * Zls_Cache_Memcached
 * @author      影浅-Seekwe
 * @email       seekwe@gmail.com
 * Date:        17/2/3
 * Time:        19:43
 */
//'memcached'     => array(
//    'class'  => 'Zls_Cache_Memcached',
//    'config' => array(//memcached服务器信息，支持多个
//                      //array("new.host.ip",11211),
//    ),
//),
use Z;
class Memcached implements \Zls_Cache
{
    private $config, $handle;
    public function __construct($config)
    {
        $this->config = $config;
    }
    public function clean()
    {
        $this->_init();
        return $this->handle->flush();
    }
    private function _init()
    {
        if (empty($this->handle)) {
            $this->handle = new \Memcached();
            foreach ($this->config as $server) {
                if ($server[2] > 0) {
                    $this->handle->addServer($server[0], $server[1], $server[2]);
                } else {
                    $this->handle->addServer($server[0], $server[1]);
                }
            }
        }
    }
    public function delete($key)
    {
        $this->_init();
        return $this->handle->delete($key);
    }
    public function get($key)
    {
        $this->_init();
        return ($data = $this->handle->get($key)) ? $data : null;
    }
    public function set($key, $value, $cacheTime = 0)
    {
        $this->_init();
        return $this->handle->set($key, $value, $cacheTime > 0 ? (time() + $cacheTime) : 0);
    }
    public function &instance($key = null, $isRead = true)
    {
        $this->_init();
        return $this->handle;
    }
    public function reset()
    {
        $this->handle = null;
        return $this;
    }
}
