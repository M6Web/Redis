<?php

namespace M6Web\Component\Redis;

use Predis;

/**
 * Class Multi
 * Allow you to send a unique command on multiple redis
 *
 * @package M6Web\Component\Redis
 */
class Multi extends Manager
{

    protected $selectedRedis = array();

    protected $multiRedis = false;

    /**
     * constructor
     *
     * @param array            $params
     * @param HashKeyInterface $hashAlgorithm Strategy for consistent hashing
     *
     * @throws Exception
     */
    public function __construct($params, $hashAlgorithm = null)
    {
        $this->setCurrentDb(1); // default hardcoded choice for the db
        if (isset($params['compress']) and ($params['compress'] === true)) {
            throw new Exception("cant use the compress option in this class");
        }
        if (isset($params['namespace'])) {
            throw new Exception("cant use the namespace option in this class");
        }

        return parent::__construct($params, $hashAlgorithm);
    }


    /**
     * select one random server
     *
     * @throws Exception
     * @return $this
     */
    public function onOneRandomServer()
    {
        $keys = array_keys($this->getServerConfig());

        do {
            $randServerRank = array_rand($keys);
            if ($redis = $this->getRedisFromServerConfig($keys[$randServerRank])) {
                $this->selectedRedis = array($keys[$randServerRank] => $redis);
            } else {
                unset($keys[$randServerRank]);
            }
        } while (!empty($keys) && empty($this->selectedRedis));

        if (empty($this->selectedRedis)) {
            throw new Exception("Can't connect to a random redis server");
        }

        $this->multiRedis = false;

        return $this;
    }

    /**
     * select all the servers
     *
     * @param bool $strict
     *
     * @throws Exception
     * @return $this
     */
    public function onAllServer($strict = true)
    {
        foreach ($this->getServerConfig() as $idServer => $config) {
            if ($redis = $this->getRedisFromServerConfig($idServer)) {
                $this->selectedRedis[$idServer] = $redis;
            } else {
                if ($strict) {
                    throw new Exception('cant connect to redis '.$idServer);
                }
            }
        }

        $this->multiRedis = true;

        return $this;
    }

    /**
     * select one server
     *
     * @param string  $idServer
     * @param boolean $strict
     *
     * @throws Exception
     * @return $this
     */
    public function onOneServer($idServer, $strict = true)
    {
        $redisList = $this->getServerConfig();

        // The server must exist..
        if (array_key_exists($idServer, $redisList)) {
            // and must be available
            if ($redis = $this->getRedisFromServerConfig($idServer)) {
                $this->selectedRedis[$idServer] = $redis;
            } else {
                if ($strict) {
                    throw new Exception('cant connect to redis ' . $idServer);
                }
            }
        } else {
            throw new Exception('unknown redis '.$idServer);
        }

        $this->multiRedis = false;

        return $this;
    }

    /**
     * select one server according to the key
     *
     * @param string $key cache key
     *
     * @return $this
     * @throws Exception when no redis server available
     */
    public function onOneKeyServer($key)
    {
        $this->selectedRedis[] = $this->getRedis($key);
        $this->multiRedis = false;

        return $this;
    }

    /**
     * magic method to the \Redis() proxy
     *
     * @param string $name      method name
     * @param array  $arguments method arguments
     *
     * @throws Exception
     * @return mixed
     */
    public function __call($name, $arguments)
    {
        if (empty($this->selectedRedis)) {
            throw new Exception("please call onOneRandomServer, onOneServer or onAllServer before ".__METHOD__);
        }

        // Call to all available servers
        if ($this->multiRedis) {
            $toReturn = [];
            foreach ($this->selectedRedis as $idServer => $redis) {
                    $toReturn[$idServer] = $this->callRedisCommand($redis, $name, $arguments);
            }
        } else {
            // Only one server
            $toReturn = $this->callRedisCommand(array_shift($this->selectedRedis), $name, $arguments);
        }

        // reinit selected Redis
        $this->selectedRedis = array();

        return $toReturn;
    }

    /**
     * call a redis command
     *
     * @param PredisProxy    $redis
     * @param string         $name
     * @param array          $arguments
     *
     * @throws Exception
     * @return mixed
     */
    protected function callRedisCommand(PredisProxy $redis, $name, $arguments)
    {
        $start = microtime(true);
        try {
            $return = call_user_func_array(array($redis, $name), $arguments);
            $this->notifyEvent($name, $arguments, microtime(true) - $start);
        } catch (Predis\PredisException $e) {
            throw new Exception("Error calling the method ".$name." : ".$e->getMessage());
        }

        return $return;
    }
}
