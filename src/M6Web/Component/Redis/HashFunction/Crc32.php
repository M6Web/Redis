<?php

namespace M6Web\Component\Redis\HashFunction;

use M6Web\Component\Redis\HashFunctionInterface;

class Crc32 implements HashFunctionInterface
{
    public function hash($key, $maxValue)
    {
        return crc32($key) % $maxValue;
    }
}
