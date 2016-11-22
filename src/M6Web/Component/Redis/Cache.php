<?php
/**
 * implement Redis for a cache
 *
 * class dealing only with set and get and propose a build-in namespace feature
 * you can have multiple redis server (see manager)
 *
 * @author Olivier Mansour
 */
namespace M6Web\Component\Redis;

use Predis;

/**
 * class specialized for a cache usage
 * you cant access here to the predis base object
 */
class Cache extends Manager
{
    const CACHE = 0; // dbname will always be 0

    /**
     * namespace used to prefix keys
     * @var string
     */
    protected $namespace = null;

    /**
     * using the multi feature (see redis.io) we will store set and get since exec is called
     * when exec is called, set and get are dispatched between servers during multiple transactions
     * @var boolean
     */
    protected $multi = false;

    /**
     * list of closures executed in multi mode
     * @var array
     */
    protected $execList = array();

    /**
     * class constructor
     * @param array $params Manager parameters
     *
     * @throws Exception
     */
    public function __construct($params)
    {
        if (!isset($params['namespace'])) {
            throw new Exception("Le parametre namespace est obligatoire");
        }
        $this->setNamespace($params['namespace']);
        parent::__construct($params);
        $this->currentDb = self::CACHE;
    }

    /**
     * set the namespace
     * @param string $v namespace (sort of)
     */
    public function setNamespace($v)
    {
        $this->namespace =  str_replace(array('\\', '?', '*', '[', ']', ':'), '', (string) $v).':';
    }

    /**
     * get the namespace
     * @return string
     */
    public function getNamespace()
    {
        return $this->namespace;
    }

    /**
     * get a value from the cache
     * @param string $key clé
     *
     * @return mixed|null Null if no value available
     */
    public function get($key)
    {
        $redis = $this->getRedis($key);
        $start = microtime(true);
        $ret = $redis->get($this->getPatternKey().$key); // ajout du pattern
        $this->notifyEvent('get', array($this->getPatternKey().$key), microtime(true) - $start);
        if ($ret and $this->getCompress()) {
            return self::uncompress($ret);
        } else {
            return $ret;
        }
    }

    /**
     * delete a key
     *
     * @param array $keys array of keys
     *
     * @return integer
     */
    public function del($keys)
    {
        if (!is_array($keys)) {
            $keys = array($keys);
        }
        $funcs = array();

        // séparation en serveur
        foreach ($keys as $key) {
            $keyP = $this->getPatternKey().$key;
            $funcs[$key] = function ($redis) use ($keyP) {
                $start = microtime(true);
                $count = $redis->del($keyP);
                if ((is_int($count)) and ($count > 0)) {
                    $this->notifyEvent('del', array($keyP), microtime(true) - $start);
                }

                return $count;
            };
        }

        if (true === $this->multi) {
            //throw new Exception("not implemented yet");
            foreach ($funcs as $k => $func) {
                $this->addToExecList($k, $func);
            }

            return $this;
        } else {
            $ret = 0;
            foreach ($funcs as $k => $func) {
                $ret += $func($this->getRedis($k)); // execution
            }

            return $ret;
        }
    }

    /**
     * set a value
     *
     * @param string  $key   la clé
     * @param string  $value valeur
     * @param integer $ttl   time to live
     *
     * @return Cache
     */
    public function set($key, $value, $ttl = null)
    {
        if ($this->getCompress()) {
            $value = self::compress($value);
        }

        $keyP = $this->getPatternKey().$key;

        if (is_null($ttl)) {
            $func = function ($redis) use ($keyP, $value) {
                $start = microtime(true);
                $redis->set($keyP, $value);
                $this->notifyEvent('set', array($keyP, $value), microtime(true) - $start);

                return $redis;
            };
        } else {
            $func = function ($redis) use ($keyP, $value, $ttl) {
                $start = microtime(true);
                $redis->setex($keyP, $ttl, $value);
                $this->notifyEvent('setex', array($keyP, $ttl, $value), microtime(true) - $start);

                return $redis;
            };
        }

        if (true === $this->multi) {
            $this->addToExecList($key, $func);
        } else {
            $func($this->getRedis($key)); // set "normal"
        }

        return $this;
    }


    /**
     * check if a key exist
     *
     * @param string $key clé
     *
     * @return boolean
     */
    public function exists($key)
    {
        $start = microtime(true);
        $ret = $this->getRedis($key)->exists($this->getPatternKey().$key);
        $this->notifyEvent('exists', array($this->getPatternKey().$key), microtime(true) - $start);

        return (boolean) $ret;
    }

    /**
     * return the type of a key
     *
     * @param string $key clé
     *
     * @return mixed
     */
    public function type($key)
    {
        $start = microtime(true);
        $ret = (string)$this->getRedis($key)->type($this->getPatternKey().$key);
        $this->notifyEvent('type', array($this->getPatternKey().$key), microtime(true) - $start);

        return $ret;
    }

    /**
     * return a key ttl
     * @param string $key clé
     *
     * @return integer
     */
    public function ttl($key)
    {
        $start = microtime(true);
        $ret = $this->getRedis($key)->ttl($this->getPatternKey().$key);
        $this->notifyEvent('ttl', array($this->getPatternKey().$key), microtime(true) - $start);

        return $ret;
    }

    /**
     * increment a value (incr)
     *
     * @param string  $key  la clé
     * @param integer $incr valeur
     *
     * @throws Exception
     * @return int
     */
    public function incr($key, $incr = 1)
    {
        $keyP = $this->getPatternKey().$key;
        $func = function ($redis) use ($keyP, $incr) {
            try {
                $start = microtime(true);
                $ret = $redis->incrby($keyP, $incr);
                $this->notifyEvent('incrby', array($keyP, $incr), microtime(true) - $start);

                return $ret;
            } catch (Predis\ServerException $e) {
                return null;
            }
        };

        if (true === $this->multi) {
            $this->addToExecList($key, $func);

            return $this;
        } else {
            if (is_null($ret = $func($this->getRedis($key)))) {
                throw new Exception("Cant increment key ".$key.", not an integer ?");
            }

            return $ret;
        }
    }

    /**
     * set the key ttl
     *
     * @param string  $key la clé
     * @param integer $ttl ttl en seconde
     *
     * @throws Exception
     * @return int
     */
    public function expire($key, $ttl)
    {
        if ($ttl <= 0) {
            throw new Exception('ttl arg cant be negative');
        }

        $keyP = $this->getPatternKey().$key;
        $func = function ($redis) use ($keyP, $ttl) {
            try {
                $start = microtime(true);
                $ret = $redis->expire($keyP, $ttl);
                $this->notifyEvent('expire', array($keyP, $ttl), microtime(true) - $start);

                return $ret;
            } catch (Predis\ServerException $e) {
                return null;
            }
        };

        // gestion du multi
        if (true === $this->multi) {
            $this->addToExecList($key, $func);

            return $this;
        } else {
            $ret = $func($this->getRedis($key));

            return $ret;
        }
    }

    /**
     * used the to build the namespace added to the keys
     * @return string
     */
    public function getPatternKey()
    {
        return $this->namespace;
    }

    /**
     * use for debugging !
     *
     * flush all keys in the namespace
     * @return integer number of deleted keys
     */
    public function flush()
    {
        $pattern = $this->getPatternKey();
        $arrReturn = $this->runOnAllRedisServer(
            function ($redis) use ($pattern) {
                $wasDeleted = 0;
                $allKeys = $redis->keys($pattern.'*'); // all keys started with the namespace
                if (count($allKeys)) {
                    $wasDeleted = $redis->del($allKeys);
                }

                return array($wasDeleted);
            }
        );

        return array_sum($arrReturn);
    }

    /**
     * watch
     *
     * @param string $key clé
     *
     * @return void
     */
    public function watch($key)
    {
        $redis = $this->getRedis($key);
        $this->notifyEvent('watch', array($this->getPatternKey().$key));
        $redis->watch($this->getPatternKey().$key);
    }

    /**
     * unwatch (flush all params watched)
     *
     * @return string
     */
    public function unwatch()
    {
        return $this->runOnAllRedisServer(
            function ($redis) {
                $this->notifyEvent('unwatch', array());

                return $redis->unwatch();
            }
        );
    }


    /**
     * allow a transactionnal execution
     *
     * @return Cache
     */
    public function multi()
    {
        $this->multi = true;
        $this->execList = array(); // purge the execList

        return $this;
    }

    /**
     * execute multiple commands
     *
     * @throws Exception
     * @return array outputs of the commands
     */
    public function exec()
    {
        if (true === $this->multi) {
            $ret         = array();
            $this->multi = false;
            // exec
            foreach ($this->execList as $todos) {
                $redis = $todos[0]['redis']; // ^^ not so secure ?
                $this->notifyEvent('multi', array());
                $redis->multi();
                foreach ($todos as $todo) {
                    if ($todo['function'] instanceof \Closure) {
                        $ret[] = $todo['function']($redis);
                    } else {
                        throw new Exception("Ce n'est pas une Closure !");
                    }
                }
                $this->notifyEvent('exec', array());
                $redis->exec();
            }
            $this->execList = array(); // purge the execList

            return $ret;
        } else {
            throw new Exception("you have to call multi before exec");
        }
    }

    /**
     * cancel a transaction
     *
     * @return Cache
     */
    public function discard()
    {
        $this->multi    = false;
        $this->execList = array(); // purge the execList

        return $this;
    }

    /**
     * return an array of server with all the keys stored in the cache
     * use only for debug
     *
     * @return array
     */
    public function getAllKeys()
    {
        $pattern = $this->getPatternKey();

        return $this->runOnAllRedisServer(
            function ($redis) use ($pattern) {
                $this->notifyEvent('keys', array($pattern.'*'));

                return $redis->keys($pattern.'*'); // toutes les clés commençant par le pattern
            }
        );
    }

    /**
     * return an array of server with all the keys stored in the cache according to $pattern
     * use only for debug
     *
     * @param string $pattern the pattern - ie : raoul*
     *
     * @return array
     */
    public function keys($pattern)
    {
        $pattern = $this->getPatternKey().$pattern;

        return $this->runOnAllRedisServer(
            function ($redis) use ($pattern) {
                $this->notifyEvent('keys', array($pattern));

                return $redis->keys($pattern);
            }
        );
    }

    /**
     * apply a Closure on all the servers
     * @param \Closure $func anonymous function
     *
     * @return array array of results returned bye redis commands
     */
    protected function runOnAllRedisServer(\Closure $func)
    {
        $ret = array();
        // loop on all servers
        foreach (array_keys($this->getServerConfig()) as $serverId) {
            if ($redis = $this->getRedisFromServerConfig($serverId)) {
                $retFunc = $func($redis);
                if (is_array($retFunc)) {
                    $ret = array_merge($ret, $retFunc);
                }
            }
        }

        return $ret;
    }

    /**
     * return an array or server with the number of keys stored on each
     *
     * @param string $idServer Id du server
     *
     * @return array / integer
     */
    public function dbSize($idServer = null)
    {
        if (is_null($idServer)) {
            $servers = $this->getServerConfig();

            $dbsize = array();
            foreach (array_keys($servers) as $idServer) {
                $redis = $this->getRedisFromServerConfig($idServer);

                $dbsize[$idServer] = $redis->dbsize();
            }
        } else {
            $redis = $this->getRedisFromServerConfig($idServer);

            $dbsize = $redis->dbsize();
        }

        return $dbsize;
    }

    /**
     * return info of a server or all
     * @param string $idServer Id du server
     *
     * @return mixed
     */
    public function info($idServer = null)
    {
        if (is_null($idServer)) {
            $servers = $this->getServerConfig();

            $info = array();
            foreach (array_keys($servers) as $idServer) {
                $redis = $this->getRedisFromServerConfig($idServer);

                $info[$idServer] = $redis->info();
            }
        } else {
            $redis = $this->getRedisFromServerConfig($idServer);

            $info = $redis->info();
        }

        return $info;
    }

    /**
     * method not allowed in cache mode
     *
     * @param string $v de toute façon j'm'en fiche
     *
     * @deprecated
     * @return object|void
     * @throws Exception
     */
    public function setCurrentDb($v)
    {
        throw new Exception("forbidden method in cache mode :".__METHOD__);
    }

    /**
     * add a task to the execution list
     *
     * @param string   $key  clé
     * @param callable $func closure à ajouter
     *
     * @return void
     */
    protected function addToExecList($key, \Closure $func)
    {
        $redis = $this->getRedis($key);
        $this->execList[md5(serialize($redis))][] = array(
            'key'      => $key,
            'function' => $func,
            'redis'    => $redis
        );
    }
}
