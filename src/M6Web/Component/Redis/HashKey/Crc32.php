<?php

namespace M6Web\Component\Redis\HashKey;

use M6Web\Component\Redis\HashKeyInterface;

class Crc32 implements HashKeyInterface
{
    public function hash($key, $maxValue)
    {
        return crc32($key) % $maxValue;
    }
}
