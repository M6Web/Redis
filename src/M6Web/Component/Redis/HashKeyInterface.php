<?php

namespace M6Web\Component\Redis;

interface HashKeyInterface
{
    /**
     * @param string $key Input key to hash.
     * @param int    $maxValue Upper bound for returned value.
     *
     * @return int A value between 0 (included) and $maxValue (excluded).
     */
    public function hash($key, $maxValue);
}