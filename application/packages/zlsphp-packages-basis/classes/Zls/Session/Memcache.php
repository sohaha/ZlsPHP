<?php
namespace Zls\Session;
/**
 * Memcache托管
 * @author      影浅-Seekwe
 * @email       seekwe@gmail.com
 * Date:        17/2/3
 * Time:        19:50
 */
class Memcache extends \Zls_Session
{
    public function init($sessionID)
    {
        ini_set('session.save_handler', 'memcache');
        ini_set('session.save_path', $this->config['path']);
    }
    public function swooleInit($sessionID)
    {
        $_SESSION = [];
    }
    public function swooleGet($key)
    {
    }
    public function swooleUnset($key)
    {
    }
    public function swooleSet($key, $value)
    {
    }
}
