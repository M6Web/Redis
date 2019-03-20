<?php

namespace M6Web\Component\Redis\HashKey;

use M6Web\Component\Redis\HashKeyInterface;

class Sha1 implements HashKeyInterface
{
    public function hash($key, $maxValue)
    {
        return hexdec(substr(sha1($key), 0, 8)) % $maxValue;
    }
}
