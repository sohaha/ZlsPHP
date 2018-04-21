<?php
namespace Zls\Cache;
/**
 * Zls_Cache_Apc
 * @author      影浅-Seekwe
 * @email       seekwe@gmail.com
 * Date:        17/2/3
 * Time:        19:42
 */
//'apc'           => array(
//    'class'  => 'Zls_Cache_Apc',
//    'config' => null//apc缓存不需要配置信息，保持null即可
//),
use Z;
class Apc implements \Zls_Cache{
    public function clean()
    {
        @apc_clear_cache();
        @apc_clear_cache("user");
        return true;
    }
    public function delete($key)
    {
        return apc_delete($key);
    }
    public function get($key)
    {
        $data = apc_fetch($key, $bo);
        if ($bo === false) {
            return null;
        }
        return $data;
    }
    public function set($key, $value, $cacheTime = 0)
    {
        return apc_store($key, $value, $cacheTime);
    }
    public function &instance($key = null, $isRead = true)
    {
        return $this;
    }
    public function reset()
    {
        return $this;
    }
}
