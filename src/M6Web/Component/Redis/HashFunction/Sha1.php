<?php

namespace M6Web\Component\Redis\HashFunction;

use M6Web\Component\Redis\HashFunctionInterface;

class Sha1 implements HashFunctionInterface
{
    public function hash($key, $maxValue)
    {
        return hexdec(substr(sha1($key), 0, 8)) % $maxValue;
    }
}
