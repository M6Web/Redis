<?php

namespace M6Web\Component\Redis\tests\units;

use \mageekguy\atoum;
use \M6Web\Component\Redis\PredisProxy as proxy;
use \M6Web\Component\Redis\Cache as redisCache;
use Predis;

class PredisProxy extends atoum\test 
{
    public function testCallConstructor()
    {
        $this
            ->given($predisClient = new \mock\Predis\Client())
            ->and($proxy = new proxy($predisClient))
            ->and($options = ['foo' => 'bar'])
            ->and($params  = ['foo2' => 'bar2'])
            ->if($proxy->callRedisConstructor($options, $params))
            ->then
                ->mock($predisClient)
                    ->call('__construct')
                    ->withArguments($options, $params)
                    ->once();
    }

    /**
     * @tags test
     */
    public function testCaller()
    {
        $predisClient = new \mock\Predis\Client();

        $predisClient->getMockController()->get = function() {
            return true;
        };

        $this
            ->given($proxy = new proxy($predisClient))
            ->if($proxy->get('foo'))
            ->then
                ->mock($predisClient)
                    ->call('get')
                    ->once();

        $this->mockGenerator->shuntParentClassCalls();
        $this->mockGenerator->orphanize('__construct');

        $connException = new \mock\Predis\Connection\ConnectionException;
        $connException->getMockController()->__toString = function() {
            return 'error';
        };
        $predisClient->getMockController()->set = function() use ($connException) {
            throw $connException;
        };

        $clientException = new \mock\Predis\ClientException;
        $clientException->getMockController()->__toString = function() {
            return 'error';
        };
        $predisClient->getMockController()->connect = function() use ($clientException) {
            throw $clientException;
        };

        $this
            ->given($proxy = new proxy($predisClient))
            ->and($proxy->setMaxConnectionLostAllowed(2))
            ->then
                ->exception(
                    function() use ($proxy) {
                        $proxy->set('foo', 'bar');
                    }
                )
                ->isInstanceOf('Predis\Connection\ConnectionException')
                ->mock($predisClient)
                    ->call('set')
                    ->thrice();

        $exceptionCount = 0;
        $predisClient->getMockController()->connect = function() use ($clientException, $exceptionCount) {
            if ($exceptionCount < 2) {
                $exceptionCount++;
                // after 2 conn attempt : return true
                throw $clientException;
            } else {
                return true;
            }
        };
        $this
            ->given($proxy = new proxy($predisClient))
            ->and($proxy->setMaxConnectionLostAllowed(12))
            ->then
                ->mock($predisClient)
                    ->call('set')
                    ->thrice();
    }
    
    /**
     * 
     */
     public function testSimulation()
     {
         $redis = new redisCache([
             'timeout' => 1,
             'server_config' => ['localhost' => ['ip' => 'localhost', 'port' => 6379]],
             'namespace' => 'test_proxy',
             'reconnect' => 0
         ]);
 
        // timeout to 10 seconds
        $predisClient = new Predis\Client(['host' => 'localhost']);
        $response = $predisClient->executeRaw(array('config', 'set', 'timeout', '10'));
        
        $this->assert
            ->object($redis->set('foo', 'raoul'));
        $this->assert
            ->string($redis->get('foo'))
            ->isEqualTo('raoul')
            ;
         
        sleep(20);
 
        $this->assert
            ->exception(
                function() use ($redis) {
                    $redis->get('foo');
                })
            ->isInstanceOf('Predis\Connection\ConnectionException');
    }
    
    /**
     * 
     */
     public function testReconnexion()
     {
         $redis = new redisCache([
             'timeout' => 1,
             'server_config' => ['localhost' => ['ip' => 'localhost', 'port' => 6379]],
             'namespace' => 'test_proxy',
             'reconnect' => 1
         ]);
 
        // timeout to 10 seconds
        $predisClient = new Predis\Client(['host' => 'localhost']);
        $response = $predisClient->executeRaw(array('config', 'set', 'timeout', '10'));
        
        $this->assert->object($redis->set('foo', 'raoul'));
        
        $this->assert
            ->string($redis->get('foo'))
            ->isEqualTo('raoul')
            ;
         
        sleep(20);
 
        $this->assert
            ->string($redis->get('foo'))
            ->isEqualTo('raoul')
            ;
    }
}