<?php
/**
 * @author Olivier Mansour
 *
 * strongly depend on predis
 *
 */

namespace M6Web\Component\Redis;

use Predis;

/**
 * Manager for Redis classes
 */
abstract class Manager
{

    /**
     * server default timeout
     * @var float
     */
    protected $timeout = 0.2;

    /**
     * read write default timeout
     * @var float
     */
    protected $read_write_timeout = 0.2;

    /**
     * array of server configuration
     * @var array
     */
    protected $serverConfig = null;

    /**
     * array of Redis object
     * @var array
     */
    protected $aliveRedis = array();

    /**
     * array of Redis object
     * @var array
     */
    protected $deadRedis = array();

    /**
     * db (as redis understand it)
     * @var integer
     */
    protected $currentDb;

    /**
     * do I use the compression
     * @var bool
     */
    private $compress;

    /**
     * event dispatcher
     * @var Object
     */
    protected $eventDispatcher = null;

    /**
     * class of the event notifier
     * @var string
     */
    protected $eventClass = null;

    /**
     * @var HashFunctionInterface
     */
    protected $hashAlgorithm = null;

    /**
     * number of reconnect if a command fail (passed to predisProxy)
     * @var int
     */
    protected $reconnect = 0;

    /**
     * name of the event
     * @var string
     */
    protected $eventName = 'redis.command';

    /**
     * constructor
     * $params = array(
     * 'timeout' => 2,
     * 'compress' => true,
     * 'reconnect' => 2
     * 'server_config' = array(
     *   'php50' => array (
     *       'ip' => '193.22.143.110',
     *       'port' => 6379,
     *       ));
     *
     * @param array                 $params Manager params
     * @param HashFunctionInterface $hashAlgorithm Strategy for consistent hashing
     *
     * @return \M6Web\Component\Redis\Manager
     */
    public function __construct($params, $hashAlgorithm = null)
    {
        $this->hashAlgorithm = $hashAlgorithm !== null ? $hashAlgorithm : new HashFunction\Crc32();
        $this->init($params);

        return $this;
    }
        
    /**
     * set the current db
     *
     * @param integer $v
     *
     * @throws Exception
     * @return object DB
     */
    public function setCurrentDb($v)
    {
        if (!is_int($v)) {
            throw new Exception("please describe the db as an integer ^^");
        }
        if ($v == Cache::CACHE) {
            throw new Exception("cant use ".Cache::CACHE." in class ".__CLASS__);
        }
        $this->currentDb = $v;

        return $this;
    }

    /**
     * get the current db
     * @throws Exception
     * @return string|int
     */
    public function getCurrentDb()
    {
        if (is_null($this->currentDb)) {
            throw new Exception("currentDb cant be null");
        }

        return $this->currentDb;
    }

    /**
     * Notify an event to the event dispatcher
     * @param string $command   The command name
     * @param array  $arguments args of the command
     * @param int    $time      exec time
     *
     * @return \M6Web\Component\Redis\Manager
     */
    public function notifyEvent($command, $arguments, $time = 0)
    {
        if ($this->eventDispatcher) {
            $className = $this->eventClass;
            $event = new $className();
            $event->setCommand($command);
            $event->setExecutionTime($time);
            $event->setArguments($arguments);
            $this->eventDispatcher->dispatch($this->eventName, $event);
        }

        return $this;
    }

    /**
     * Set an event dispatcher to notify redis command
     * @param Object $eventDispatcher The eventDispatcher object, which implement the notify method
     * @param string $eventClass      The event class used to create an event and send it to the event dispatcher
     *
     * @throws Exception
     * @return \M6Web\Component\Redis\Manager
     */
    public function setEventDispatcher($eventDispatcher, $eventClass)
    {
        if (!is_object($eventDispatcher) || !method_exists($eventDispatcher, 'dispatch')) {
            throw new Exception("The EventDispatcher must be an object and implement a dispatch method");
        }

        if (!class_exists($eventClass) ||
            !method_exists($eventClass, 'setCommand') ||
            !method_exists($eventClass, 'setArguments') ||
            !method_exists($eventClass, 'setExecutionTime')
            ) {
            $msg = "The Event class : ".$eventClass." must implement the setCommand, ";
            $msg .= "setExecutionTime and the setArguments method";
            throw new Exception($msg);
        }
        $this->eventDispatcher = $eventDispatcher;
        $this->eventClass      = $eventClass;

        return $this;
    }

    /**
     * init for the class
     * @param array $params confg array
     *
     * @throws Exception
     * @return \M6Web\Component\Redis\Manager
     * @throw Exception
     */
    protected function init($params)
    {
        // check serverConfig
        if (!isset($params['server_config'])) {
            throw new Exception("parameter serverConfig is mandatory");
        }

        ksort($params['server_config']);
        $this->setServerConfig($params['server_config']);
        if (isset($params['timeout'])) {
            $this->timeout = $params['timeout'];
        }
        if (isset($params['read_write_timeout'])) {
            $this->read_write_timeout = $params['read_write_timeout'];
        } else {
            // use the same timeout
            $this->read_write_timeout = $this->timeout;
        }
        if (isset($params['compress']) and is_bool($params['compress'])) {
            $this->compress = $params['compress'];
        }

        if (isset($params['reconnect']) and is_int($params['reconnect'])) {
            $this->reconnect = $params['reconnect'];
        }

        if (isset($params['event_name'])) {
            $this->eventName = (string) $params['event_name'];
        }

        return $this;
    }

    /**
     * retourn compress param
     * @return bool
     */
    public function getCompress()
    {
        return $this->compress;
    }

    /**
     * check the server config
     * @param array $servers host_config
     *
     * @return boolean
     */
    protected function checkServerConfig($servers)
    {
        if (!is_array($servers)) {
            return false;
        }
        foreach ($servers as $serverId => $server) {
            if (!is_string($serverId)) {
                return false;
            }
            if (!isset($server['ip']) or !isset($server['port'])) {
                return false;
            }
        }

        return true;
    }

    /**
     * getTimeout
     *
     * @return float
     */
    public function getTimeout()
    {
        return (float) $this->timeout;
    }

    /**
     * getReadWriteTimeout
     *
     * @return float
     */
    public function getReadWriteTimeout()
    {
        return (float) $this->read_write_timeout;
    }

    /**
     * get the server info against servernam or all the config
     * @param int $servername clé
     *
     * @return array
     */
    public function getServerConfig($servername = null)
    {
        if (is_null($servername)) {
            return $this->serverConfig;
        } else {
            return $this->serverConfig[$servername];
        }
    }

    /**
     * set the server config
     * @param array $servers config
     * @param bool  $check   do I have to check the config
     *
     * @throws Exception
     * @return \M6Web\Component\Redis\Manager
     */
    protected function setServerConfig($servers, $check = true)
    {
        if ($check and (!self::checkServerConfig($servers))) {
            throw new Exception("Le parametre serverConfig est mal formé");
        }
        // allow set only if the class var is null (one init only)
        if (is_null($this->serverConfig)) {
            $this->serverConfig = $servers;
        }

        return $this;
    }

    /**
     * return a server according to the redis key passed
     * @param string $key     server name
     * @param array  $servers array of servers
     *
     * @return mixed
     */
    protected function getServerId($key, $servers = null)
    {
        if (is_null($servers)) {
            $servers = $this->getServerConfig();
        }
        $serverKeys = array_keys($servers);

        return $serverKeys[$this->hashAlgorithm->hash($key, count($serverKeys))];
    }

    /**
     * return a Redis object according to the key
     * @param string $key     cache key
     * @param array  $servers servers
     *
     * @throws Exception
     * @return object
     */
    protected function getRedis($key, $servers = null)
    {
        if (is_null($servers)) {
            $servers = $this->getServerConfig(); // all the servers
        }
        if (0 == count($servers)) {
            throw new Exception("No redis server available ! ");
        }
        $idServer = $this->getServerId($key, $servers);

        if (!($redis = $this->getRedisFromServerConfig($idServer))) {
            $this->notifyEvent('redis_host_on_error', array($idServer));
            // find another server !!!
            unset($servers[$idServer]); // supress the server

            return $this->getRedis($key, $servers); // RECURSION ^^
        }
        $redis->select($this->getCurrentDb());

        return $redis;
    }

    /**
     * buid a redis server with a config
     * @param string $idServer server id in the configuration
     *
     * @return object|false
     */
    public function getRedisFromServerConfig($idServer)
    {
        // redis already marked dead
        if (array_key_exists($idServer, $this->deadRedis)) {
            return false;
        }
        // redis already marked alive
        if (array_key_exists($idServer, $this->aliveRedis)) {
            if (!$this->aliveRedis[$idServer]->isConnected()) {
                $this->deadRedis[$idServer] = 1;

                return false;
            }

            return $this->aliveRedis[$idServer];
        }

        $redis = $this->getNewRedis();
        if ($this->connectServer($redis, $this->getServerConfig($idServer))) {
            $this->aliveRedis[$idServer] = $redis;

            return $redis;
        } else {
            $this->deadRedis[$idServer] = 1;

            return false;
        }
    }

    /**
     * return a Predis object
     *
     * @throws Exception
     * @return PredisProxy
     */
    protected function getNewRedis()
    {
        $proxy = new PredisProxy();

        return $proxy->setMaxConnectionLostAllowed($this->reconnect);
    }

    /**
     * connecte un server
     * @param object|\Predis\Client $redis  \Predis\Client
     * @param array                 $server array('ip' => , 'port' => , 'timeout' =>)
     *
     * @return boolean
     */
    protected function connectServer(PredisProxy $redis, $server)
    {
        try {
            $redis->callRedisConstructor(array(
                'host' => $server['ip'],
                'port' => (int) $server['port'],
                'timeout' => $this->getTimeout(),
                'read_write_timeout' => $this->getReadWriteTimeout()
                ));
                // check if we are connected
            $redis->connect();

            return true;
        } catch (Predis\Connection\ConnectionException $e) {
            return false;
        }

    }

    /**
     * compress a string
     * @param string $data a data to compress
     *
     * @return string
     */
    protected static function compress($data)
    {
        return gzcompress($data);
    }

    /**
     * uncompress a string
     * @param string $data data to uncompress
     *
     * @return string
     */
    protected static function uncompress($data)
    {
        return gzuncompress($data);
    }


    /**
     * forget all server marker dead or alive
     *
     * @return $this
     */
    public function forgetDeadOrAliveRedis()
    {
        $this->deadRedis  = array();
        $this->aliveRedis = array();

        return $this;
    }
}
