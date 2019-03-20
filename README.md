# component used to access Redis


## install

```shell
$ curl -s http://getcomposer.org/installer | php
$ php composer.phar install
```

## feature

Fire event on each redis command when an eventDispatcher is setted.

```
$this->setEventDispatcher($eventDispatcher, $eventClass);
```

### cache mode

```php
M6Web\Component\Redis\Cache
```

Send redis command to different server with a simple consistent hashing algorithm. Ignore server not responding.

When a redis server fall, keys are dispatched on the other servers.

```
$server_config = array(
        'php50' => array (
            'ip' => '127.0.0.1',
            'port' => 6379,
            ),
'phpraoul' => array (  // bad server
    'ip' => '1.2.3.4',
    'port' => 6379,
    ),
'php51' => array (
    'ip' => '127.0.0.1',
    'port' => 6379,
    )
);
$redis = new redis\Cache(array(
    'timeout' => self::TIMEOUT,
    'server_config' => $server_config,
    'namespace' => self::SPACENAME
    ));
$redis->set('foo', 'bar');
$foo = $redis->get('foo');
if ($redis->exists('raoul')) die('ho mon dieu il existe vraiment !');
$redis->multi()->set('foo', 'bar2');
$redis->watch('raoul');
$raoul = UnClasse::WorkHard();
$redis->set('raoul', $raoul); // wont do anything if the raoul key is modified
$redis->exec(); // execute all sets since the multi instructions
$redis->incr('compteur');
$redis-decr('compteur');
```

Check unit test for more informations.

You can implement your own consistent hashing using `HashFunctionInterface` or use provided ones: `Crc32` (default), `Sha1`.

### db mode

```php
M6Web\Component\Redis\DB
```

Access to the predis object simply calling the predis methods.

### Multi mode

```php
M6Web\Component\Redis\Multi
```

Send redis command to multiple server or send command to a random redis server.

```php
$server_config = array(
        'php50' => array (
            'ip' => 'x.x.x.1',
            'port' => 6379,
            ),
        'php51' => array (
            'ip' => 'x.x.x.2',
            'port' => 6379,
            ),
);
$redis = new redis\Multi(['timeout' => 0.1,
                         'server_config' => $server_config]);

try{
    $redis->onAllServer($strict = true)->set('foo', 'bar');
} catch(redis\Exception $e) {
    die('error occured on setting foo bar on a server');
}
// strict mode disabled, don't care about server availability
$redis->onAllServer($strict = false)->set('foo', 'bar');

// read value on a random server
try {
    $value = $redis->onOneRandomServer()->get('foo');
} catch(redis\Exception $e) {
    die('no server available');
}
```


## timeouts

A connection timeout is availaible through the `timeout` parameter. A read write timeout can be specified with the `read_write_timeout` parameter (by default, equal to the connection timeout).

```php
$redis = new redis\Cache(array(
    'timeout' => 0.5,
    'read_write_timeout' => 0.2,
    'server_config' => $server_config,
    'namespace' => self::SPACENAME
    ),
);
```

## tests


```shell
./vendor/bin/atoum -d tests
```

Unfortunatly you need redis setuped on localhost for all the tests working.



## TODO
* use SPLQueue
* refactor unit tests
