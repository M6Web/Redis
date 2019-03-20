<?php
/**
 * This class require a redis setuped on localhost
 */
namespace M6Web\Component\Redis\tests\units;

require_once __DIR__.'/CacheTest.php';

use \mageekguy\atoum;
use \M6Web\Component\Redis;

/**
 * test class
 */
class Cache extends atoum\test
{

    const SPACENAME = 'testCacheCache';

    const TIMEOUT = 0.2;

    private function getServerConfig($config)
    {
        if ($config == 'one') {
            return array(
                'php50' => array (
                    'ip' => '127.0.0.1',
                    'port' => 6379,
                    ));
        }
        if ($config == 'wrong') {
            return array(
                'phpraoul' => array (  // wrong server
                    'ip' => '1.2.3.4',
                    'port' => 6379,
                    ),
                );
        }
        if ($config == 'many') {
            return array(
                'php50' => array (
                    'ip' => '127.0.0.1',
                    'port' => 6379,
                    ),
                'phpraoul' => array (  // wrong server
                    'ip' => '1.2.3.4',
                    'port' => 6379,
                    ),
                'php51' => array (
                    'ip' => '127.0.0.1',
                    'port' => 6379,
                    )
                );
        }
        throw new \Exception("one, many or wrong can be accessed via ".__METHOD__." not : ".$config);
    }

    /**
     * [testGetServerId description]
     *
     * @tags noconnect
     *
     * @return void
     */
    public function testGetServerId()
    {
        $server_config = $this->getServerConfig('many');
        $redis = new Redis\CacheTest(array(
            'timeout' => self::TIMEOUT,
            'server_config' => $server_config,
            'namespace' => self::SPACENAME
            ));
        $this->assert
            ->string($redis->MyGetServerId('raoul'))
            ->isEqualTo('php50');
        $this->assert
            ->string($redis->MyGetServerId('foo'))
            ->isEqualTo('phpraoul');
        $this->assert
            ->string($redis->MyGetServerId('bar2'))
            ->isEqualTo('php51');
    }


    public function testSimpleGet()
    {
        $server_config = $this->getServerConfig('one');
        $redis = new Redis\Cache(array(
            'timeout' => self::TIMEOUT,
            'server_config' => $server_config,
            'namespace' => self::SPACENAME
            )
        );

        $this->assert
            ->variable($redis->get(__METHOD__."rrrrr"))
            ->isNull();
    }


    public function manySetGetDataProvider()
    {
         return array(
            array('test', 'chuck'),
            array('test', 'norris'),
            array('fool', 'bar'),
            array('fool', 'raoul')
      );
    }

    /**
     * @tags ManySetGetPredis
     * @dataProvider manySetGetDataProvider
     */
    public function testManySetGetPredis($foo, $bar)
    {
        $server_config = $this->getServerConfig('many');
        $redis = new Redis\Cache(array(
            'timeout' => self::TIMEOUT,
            'server_config' => $server_config,
            'namespace' => self::SPACENAME
            )
        );
        // setting value
        $this->assert
            ->object($redis->set(__METHOD__.$foo, $bar));
        $this->assert
            ->string($redis->get(__METHOD__.$foo))
            ->isEqualTo($bar);

        // compressed
        $redis = new redis\CacheTest(array(
            'timeout' => self::TIMEOUT,
            'compress' => true,
            'server_config' => $server_config,
            'namespace' => self::SPACENAME
            )
        );
        // setting value
        $this->assert
            ->object($redis->set(__METHOD__.$foo, $bar));
        $this->assert
            ->string($redis->get(__METHOD__.$foo))
            ->isEqualTo($bar);
    }

    /**
     *
     * @tags rand
     *
     * [getSetRedisPRedis description]
     * @return [type]
     */
    public function testGetSetRand()
    {
        $server_config = $this->getServerConfig('many');
        $redis = new Redis\CacheTest(array(
            'timeout' => self::TIMEOUT,
            'server_config' => $server_config,
            'namespace' => self::SPACENAME
            ));
        $rnd = rand(0, 1000);
        $this->assert
            ->object($redis->set('raoul', $rnd))
            ->isEqualTo(true)
            ->string($redis->get('raoul'))
            ->isEqualTo((string) $rnd)
            ->boolean(is_numeric($redis->get('raoul')))
            ->isEqualTo(true);
    }

    /**
     * @tags noconnect
     *
     * test des trucs de base sur la conf
     * @return [type]
     */
    public function testConfig()
    {
        $server_config = $this->getServerConfig('many');
        $redis = new Redis\CacheTest(array(
            'timeout' => self::TIMEOUT,
            'server_config' => $server_config,
            'namespace' => self::SPACENAME
            ));
        $this->assert
            ->array($redis->getServerConfig())
            ->isEqualTo($server_config)
            ->array($redis->getServerConfig('php51'))
            ->isEqualTo(
                $server_config['php51']
                )
            ->integer($redis->getCurrentDb())
            ->isEqualTo(redis\Cache::CACHE)
            ->float($redis->getTimeout())
            ->isEqualTo(self::TIMEOUT);

        // without timeout
        $redis = new redis\Cache(array(
            'server_config' => $server_config,
            'namespace' => self::SPACENAME
            ));
        $this->assert
            ->float($redis->getTimeout());

        // test with read write timeout
        $redis = new redis\Cache(array(
                'server_config' => $server_config,
                'namespace' => self::SPACENAME,
                'timeout' => self::TIMEOUT,
                'read_write_timeout' => self::TIMEOUT + 0.2
            ));
        $this->assert
            ->float($redis->getReadWriteTimeout())
            ->isEqualTo($redis->getTimeout() + 0.2);
    }

    /**
     * @tags phpredis
     * [testSetGetOneServer description]
     * @return void
     */
    public function testSetGetOneServer()
    {
        $server_config = $this->getServerConfig('one');
        $redis = new Redis\CacheTest(array(
            'timeout' => self::TIMEOUT,
            'server_config' => $server_config,
            'namespace' => self::SPACENAME
            ));
        $this->assert
            ->object($redis->set(__METHOD__.'foo', 'bar'));
        $this->assert
            ->string($redis->get(__METHOD__.'foo'))
            ->isEqualTo('bar');
    }

    /**
     * [testErrorSettingAValue description]
     * @return void
     */
    public function testErrorSettingAValue()
    {
        $server_config = $this->getServerConfig('wrong');
        $redis = new Redis\CacheTest(array(
            'timeout' => self::TIMEOUT,
            'server_config' => $server_config,
            'namespace' => self::SPACENAME
            ));
        $this->assert
            ->exception(
                function() use ($redis) {
                    $redis->set('foo', 'bar');
                })
            ->isInstanceOf('\M6Web\Component\Redis\Exception');
    }

    /**
     * [testErrorSettingAValuePredis description]
     * @return void
     */
    public function testErrorSettingAValuePredis()
    {
        $server_config = $this->getServerConfig('wrong');
        $redis = new Redis\Cache(array(
            'timeout' => self::TIMEOUT,
            'server_config' => $server_config,
            'namespace' => self::SPACENAME
            ), 'predis', true);
        $this->assert
            ->exception(
                function() use ($redis) {
                    $redis->set('foo', 'bar');
                })
            ->isInstanceOf('\M6Web\Component\Redis\Exception');
    }

    /**
     * @tags flush
     * [testCacheFlush description]
     * @return void
     */
    public function testCacheFlush()
    {
        $server_config = $this->getServerConfig('many');
        $redis = new Redis\CacheTest(array(
            'timeout' => self::TIMEOUT,
            'server_config' => $server_config,
            'namespace' => self::SPACENAME.__METHOD__
            ));
        $this->assert
            ->object($redis->set('foo', 'tobeflushed'));
        $this->assert
            ->string($redis->get('foo'))
            ->isEqualTo('tobeflushed')
            ->integer($redis->flush()) // flush
            ->isEqualTo(1);
        $this->assert
            ->object($redis->set('foo', 'tobeflushed'))
            ->object($redis->set('foo2', 'tobeflushed'))
            ->object($redis->set('foo3', 'tobeflushed'))
            ->integer($redis->flush()) // flush
            ->isEqualTo(3);
        $this->assert
            ->variable($redis->get('foo'))
            ->isNull()
            ->integer($redis->flush())
            ->isEqualTo(0);
    }

    /**
     * @tags phpredis
     * [testNamespace description]
     * @return void
     */
    public function testNamespace()
    {
        $server_config = $this->getServerConfig('one');
        $redis = new Redis\CacheTest(array(
            'timeout' => self::TIMEOUT,
            'server_config' => $server_config,
            'namespace' => self::SPACENAME.'1'
            ));
        $this->assert
            ->object($redis->set('foo', 'bar'));
        $this->assert
            ->string($redis->get('foo'))
            ->isEqualTo('bar');
        $redis->setNamespace(self::SPACENAME.'2');
        $this->assert
            ->variable($redis->get('foo'))
            ->isNull();
    }

    /**
     * test expiration
     * @return void
     */
    public function testExpire()
    {
        $server_config = $this->getServerConfig('one');
        $redis = new Redis\CacheTest(array(
            'timeout' => self::TIMEOUT,
            'server_config' => $server_config,
            'namespace' => self::SPACENAME.__METHOD__
            ));
        $this->assert
            ->object($redis->set('foo', 'tobeexpired', 5));
        $this->assert
            ->string($redis->get('foo'))
            ->isEqualTo('tobeexpired');
        sleep(6);
        $this->assert
            ->variable($redis->get('foo'))
            ->isNull();
    }

    public function testExpireFunction()
    {
        $server_config = $this->getServerConfig('one');
        $redis = new Redis\CacheTest(array(
            'timeout' => self::TIMEOUT,
            'server_config' => $server_config,
            'namespace' => self::SPACENAME.__METHOD__
        ));

        $this->assert
            ->exception(function () use ($redis) {
                $redis->expire('raoul', -2);
            });
    }

    /**
     * @tags watch
     * [watch description]
     * @return [type]
     */
    public function testWatch()
    {
        $server_config = $this->getServerConfig('one');
        $redis = new Redis\Cache(array(
            'timeout' => self::TIMEOUT,
            'server_config' => $server_config,
            'namespace' => self::SPACENAME
            ));
        $this->assert
        ->variable($redis->set('toto', 'tata'))
        ->variable($redis->watch('toto'))
        ->array($t = $redis->unwatch('toto'))
        ->integer(count($t))
        ->isIdenticalTo(0); // renvoi rien
    }

    /**
     * @tags multi
     *
     * [multi description]
     * @return [type]
     */
    public function testMulti()
    {
        // $this->enableDebug();

        $server_config = $this->getServerConfig('many');
        $redis = new Redis\Cache(array(
            'timeout' => self::TIMEOUT,
            'server_config' => $server_config,
            'namespace' => self::SPACENAME.__METHOD__."v0.8.0"
            ));
        $redis->flush();
        $this->assert
            ->object($redis->multi()
                ->set('foo0', 'bar')
                ->set('foo02', 'bar2')
                ->set('foo03', 'bar3')); // il manque le exec
        $this->assert
            ->integer(count($redis->getAllKeys()))
            ->isEqualTo(0) // rien n'a été inséré
            ->variable($redis->get('foo'))
            ->isNull();
        $this->assert
            ->array($redis->multi()
                ->set('foo', 'bar')
                ->set('foo2', 'bar2')
                ->set('foo3', 'bar3')
                ->exec());
        $this->assert
            ->variable($redis->get('foo0'))
            ->isNull();
        $this->assert
            ->integer(count($redis->getAllKeys()))
            ->isEqualTo(6)
            ->integer(count($redis->keys('foo*')))
            ->isEqualTo(6)
            ->integer(count($redis->keys('raouldelalalal*')))
            ->isEqualTo(0)
            ->string($redis->get('foo'))
            ->isEqualTo('bar')
            ->string($redis->get('foo2'))
            ->isEqualTo('bar2')
            ->string($redis->get('foo3'))
            ->isEqualTo('bar3');
        $this->assert
            ->exception(
                function() use ($redis) {
                    $redis->set('foo', 'bar')->exec();
                })
            ->isInstanceOf('\M6Web\Component\Redis\Exception');
        $redis->flush();
        $this->assert
            ->object($redis->multi()
                ->set('toto', 'bar')
                ->set('toto2', 'bar2')
                ->set('toto3', 'bar3'));
           $this->assert
                  ->object($redis->discard());
        $this->assert->array(
                $redis->multi()
                ->set('toto', 'new_bar')
                ->set('toto2', 'new_bar2')
                ->exec());
        $this->assert
            ->boolean($redis->exists('toto3'))
            ->isEqualTo(false)
            ->string($redis->get('toto2'))
            ->isEqualTo('new_bar2');
        $redis->set('toto3', 'new_bar3');
        $this->assert
            ->array(
                $redis->multi()
                ->del(array('toto', 'toto2'))
                ->del('toto3')
                ->exec()
            )
            ->boolean($redis->exists('toto'))
            ->isEqualTo(false)
            ->boolean($redis->exists('toto2'))
            ->isEqualTo(false)
            ->boolean($redis->exists('toto3'))
            ->isEqualTo(false);

    }

    /**
     * @tags exists
     * [exists description]
     * @return void
     */
    public function testExists()
    {
        $server_config = $this->getServerConfig('one');
        $redis = new Redis\Cache(array(
            'timeout' => self::TIMEOUT,
            'server_config' => $server_config,
            'namespace' => self::SPACENAME.__METHOD__
            ));
        $redis->set('foo', 'bar');
        $this->assert
            ->boolean($redis->exists('uneclequinexistepasetsielleexisteLOL'))
            ->isEqualTo(false)
            ->boolean($redis->exists('foo'))
            ->isEqualTo(true);
    }

    /**
     * @tags del
     * @return void
     */
    public function testDel()
    {
        $server_config = $this->getServerConfig('many');
        $redis = new Redis\Cache(array(
            'timeout' => self::TIMEOUT,
            'server_config' => $server_config,
            'namespace' => self::SPACENAME.__METHOD__
            ));
        //$redis->flush();
        $this->assert
            ->variable($redis->set('foo', 'kikoolol'))
            ->string($redis->get('foo'))
            ->isEqualTo('kikoolol')
            ->integer($redis->del('foo'))
            ->isEqualTo(1)
            ->variable($redis->get('foo'))
            ->isNull();
    }

    /**
     * @tags incr
     *
     * [testIncr description]
     * @return void
     */
    public function testIncr()
    {
        $server_config = $this->getServerConfig('many');
        $redis = new Redis\Cache(array(
            'timeout' => self::TIMEOUT,
            'server_config' => $server_config,
            'namespace' => self::SPACENAME.__METHOD__
            ));
        $redis->flush();

        $redis->set('foo', 'bar');
        $this->assert
             ->exception(
                function() use ($redis) {
                    $redis->incr('foo');
                })
             ->isInstanceOf('Predis\Response\ServerException');
        $redis->del('foo');
        $this->assert
            ->integer($redis->incr('chuck'))
            ->isEqualTo(1)
            ->integer($redis->incr('chuck'))
            ->isEqualTo(2)
            ->integer($redis->incr('chuck', 2))
            ->isEqualTo(4)
            ->integer($redis->incr('chuck', -1))
            ->isEqualTo(3)
            ->integer($redis->incr('chuck', -2))
            ->isEqualTo(1);
        $redis->del('chuck');
        $this->assert
            ->array(
                $redis->multi()
                ->incr('chuck')
                ->incr('chuck')
                ->incr('chuck', 2)
                ->exec()
                );
        $this->assert->integer((int) $redis->get('chuck'))
            ->isEqualTo(4);

    }

    /**
     * testTtl feature
     * @return void
     */
    public function testTtl()
    {
        $server_config = $this->getServerConfig('one');
        $redis = new Redis\Cache(array(
            'timeout' => self::TIMEOUT,
            'server_config' => $server_config,
            'namespace' => self::SPACENAME.__METHOD__
            ));
        $redis->set('foo', 'bar', 10);
        $this->assert
            ->integer($redis->ttl('foo'))
            ->isEqualTo(10)
        ;
    }

    /**
     * Test type
     *
     * @return void
     */
    public function testType()
    {
        $serverConfig = $this->getServerConfig('one');
        $redis = new Redis\Cache(array(
            'timeout' => self::TIMEOUT,
            'server_config' => $serverConfig,
            'namespace' => self::SPACENAME.__METHOD__
        ));
        $redis->set('foo', 'bar', 10);

        $this
            ->assert
            ->string($redis->type('foo'))
            ->isEqualTo('string')
            ->string($redis->type('raouroauroauroauroa'))
            ->isEqualTo('none');

        $redis->set('foo', 42, 10);

    }

    /**
     * Test info
     *
     * @return void
     */
    public function testInfo()
    {
        $serverConfig = $this->getServerConfig('one');
        $redis = new Redis\Cache(array(
            'timeout' => self::TIMEOUT,
            'server_config' => $serverConfig,
            'namespace' => self::SPACENAME.__METHOD__
        ));
        $redis->set('foo', 'bar', 10);

        $keysConfig = array_keys($serverConfig);
        $keyConfig = array_shift($keysConfig);

        $this
            ->assert
            ->array($info = $redis->info())
            ->hasKey($keyConfig)
            ->array($info[$keyConfig])
            ->hasKeys(array('Server', 'Memory', 'Clients'));
    }

    /**
     * Test dbsize
     *
     * @return void
     */
    public function testDbsize()
    {
        $serverConfig = $this->getServerConfig('one');
        $redis = new Redis\Cache(array(
            'timeout' => self::TIMEOUT,
            'server_config' => $serverConfig,
            'namespace' => self::SPACENAME.__METHOD__
        ));
        $redis->set('foo', 'bar', 10);

        $keysConfig = array_keys($serverConfig);
        $keyConfig = array_shift($keysConfig);

        $this
            ->assert
            ->array($dbsize = $redis->dbsize())
            ->hasKey($keyConfig)
            ->integer($dbsize[$keyConfig]);
    }

    public function testHashing()
    {
        $server_config = $this->getServerConfig('many');
        $redis = new Redis\CacheTest(array(
            'timeout' => self::TIMEOUT,
            'server_config' => $server_config,
            'namespace' => self::SPACENAME.__METHOD__
        ));

        $this
            ->assert
            ->if($redis->set('raoul', 'test'))
            ->array($redis->getDeadRedis())
            ->isEmpty();
        $this
            ->assert
            ->if($redis->set('foo', 'test'))
            ->array($redis->getDeadRedis())
            ->hasSize(1);
        $this
            ->assert
            ->if($redis->set('bar3', 'test'))
            ->array($redis->getDeadRedis())
            ->hasSize(1)
        ;
    }

}
