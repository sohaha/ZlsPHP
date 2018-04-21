<?php
namespace Zls\Session;
/**
 * Redis托管
 * @author        影浅
 * @email         seekwe@gmail.com
 * @copyright     Copyright (c) 2015 - 2017, 影浅, Inc.
 * @link          ---
 * @since         v0.0.1
 * @updatetime    2018-1-26 18:04:33
 */
use Z;
class Redis extends \Zls_Session
{
    private $sessionHandle;
    private $sessionID = '';
    private $sessionConfig = [];
    public function init($sessionID)
    {
        ini_set('session.save_handler', 'redis');
        ini_set('session.save_path', $this->config['path']);
    }
    public function swooleInit($sessionID)
    {
        $sessionConfig = Z::config()->getSessionConfig();
        $path = $this->config['path'];
        $config = [
            'class'  => 'Zls\Cache\Redis',
            'config' => [],
        ];
        $masters = \explode(',', $path);
        foreach ($masters as $k => $master) {
            $parseUrl = parse_url($master);
            $auth = z::arrayGet($parseUrl, 'auth');
            $config['config'][] = [
                'master' =>
                    [
                        'type'     => 'tcp',
                        'prefix'   => z::arrayGet($parseUrl, 'prefix', 'ZLSESSION'),
                        'sock'     => '',
                        'host'     => z::arrayGet($parseUrl, 'host', '127.0.0.1'),
                        'port'     => z::arrayGet($parseUrl, 'port', 6379),
                        'password' => $auth ? \urldecode($auth) : null,
                        'timeout'  => z::arrayGet($parseUrl, 'timeout', 3) * 1000,
                        'retry'    => z::arrayGet($parseUrl, 'retry', 100),
                        'db'       => z::arrayGet($parseUrl, 'database', 0),
                    ],
                'slaves' => [],
            ];
        }
        $this->sessionID = $sessionID;
        $this->sessionHandle = Z::cache($config);
        $this->sessionConfig = $sessionConfig;
        $session = @unserialize($this->sessionHandle->get($this->sessionID));
        $_SESSION = !is_null($session) && is_array($session) ? $session : [];
    }
    public function swooleGet($key = null)
    {
        return $_SESSION;
    }
    public function swooleUnset($key)
    {
        return $key ? $this->swooleSet(null) : $this->sessionHandle->delete($this->sessionID);
    }
    public function swooleSet($key, $value = null)
    {
        $sessionName = $this->sessionConfig['session_name'];
        $sessionID = z::cookieRaw($sessionName);
        z::setCookieRaw($sessionName, $sessionID, time() + $this->sessionConfig['lifetime'], '/');
        return $this->sessionHandle->set($this->sessionID, serialize($_SESSION), $this->sessionConfig['lifetime']);
    }
}
