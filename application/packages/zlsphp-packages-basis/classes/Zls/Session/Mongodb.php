<?php
namespace Zls\Session;
/**
 * Mongodb托管
 * @author      影浅-Seekwe
 * @email       seekwe@gmail.com
 * Date:        17/2/3
 * Time:        19:49
 */
/*
return new Zls_Session_Mongodb(array(
    'host' => '127.0.0.1', //mongodb主机地址
    'port' => 27017, //端口
    'user' => 'root',
    'password' => '',
    'database' => 'local', //   MongoDB 数据库名称
    'collection' => 'sessions', //   MongoDB collection名称
    'persistent' => false, // 是否持久连接
    'persistentId' => 'ZlsterMongoSession', // 持久连接id
    // 是否支持 replicaSet
    'replicaSet' => false,
	)
);
*/
use Z;
class Mongodb extends \Zls_Session
{
    private $__mongo_collection = null;
    private $__current_session = null;
    private $__mongo_conn = null;
    public function __construct($configFileName)
    {
        parent::__construct($configFileName);
        $cfg = Z::config()->getSessionConfig();
        $this->config['lifetime'] = $cfg['lifetime'];
    }
    public function init($sessionID)
    {
        session_set_save_handler([&$this, 'open'], [&$this, 'close'], [&$this, 'read'],
            [&$this, 'write'], [&$this, 'destroy'], [&$this, 'gc']
        );
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
    public function open($session_path, $session_name)
    {
        $this->connect();
        return true;
    }
    public function connect()
    {
        if (is_object($this->__mongo_collection)) {
            return;
        }
        $connection_string = sprintf('mongodb://%s:%s', $this->config['host'], $this->config['port']);
        if ($this->config['user'] != null && $this->config['password'] != null) {
            $connection_string = sprintf('mongodb://%s:%s@%s:%s/%s', $this->config['user'], $this->config['password'],
                $this->config['host'], $this->config['port'], $this->config['database']
            );
        }
        $opts = ['connect' => true];
        if ($this->config['persistent'] && !empty($this->config['persistentId'])) {
            $opts['persist'] = $this->config['persistentId'];
        }
        if ($this->config['replicaSet']) {
            $opts['replicaSet'] = $this->config['replicaSet'];
        }
        $class = 'MongoClient';
        if (!class_exists($class)) {
            $class = 'Mongo';
        }
        $this->__mongo_conn = $object_conn = new $class($connection_string, $opts);
        $object_mongo = $object_conn->{$this->config['database']};
        $this->__mongo_collection = $object_mongo->{$this->config['collection']};
        if ($this->__mongo_collection == null) {
            throw new \Zls_Exception_500('can not connect to mongodb server');
        }
    }
    /**
     * @return bool
     */
    public function close()
    {
        $this->__mongo_conn->close();
        return true;
    }
    /**
     * @param $session_id
     * @return string
     */
    public function read($session_id)
    {
        $result = null;
        $ret = '';
        $expiry = time();
        $query['_id'] = $session_id;
        $query['expiry'] = ['$gte' => $expiry];
        $result = $this->__mongo_collection->findone($query);
        if ($result) {
            $this->__current_session = $result;
            $result['expiry'] = time() + $this->config['lifetime'];
            $this->__mongo_collection->update(["_id" => $session_id], $result);
            $ret = $result['data'];
        }
        return $ret;
    }
    public function write($session_id, $data)
    {
        $result = true;
        $expiry = time() + $this->config['lifetime'];
        $session_data = [];
        if (empty($this->__current_session)) {
            $session_data['_id'] = $session_id;
            $session_data['data'] = $data;
            $session_data['expiry'] = $expiry;
        } else {
            $session_data = (array)$this->__current_session;
            $session_data['data'] = $data;
            $session_data['expiry'] = $expiry;
        }
        $query['_id'] = $session_id;
        $record = $this->__mongo_collection->findOne($query);
        if ($record == null) {
            $this->__mongo_collection->insert($session_data);
        } else {
            $record['data'] = $data;
            $record['expiry'] = $expiry;
            $this->__mongo_collection->save($record);
        }
        return true;
    }
    public function destroy($session_id)
    {
        unset($_SESSION);
        $query['_id'] = $session_id;
        $this->__mongo_collection->remove($query);
        return true;
    }
    public function gc($max = 0)
    {
        $query = [];
        $query['expiry'] = [':lt' => time()];
        $this->__mongo_collection->remove($query, ['justOne' => false]);
        return true;
    }
}
