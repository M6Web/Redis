<?php

namespace M6Web\Component\Redis\HashKey\tests\units;

require_once __DIR__ . '/../HashKey.php';

use M6Web\Component\Redis\tests\units\HashKey;

class Sha1 extends HashKey
{
    protected function createHashKey()
    {
        return new \M6Web\Component\Redis\HashKey\Sha1();
    }

}
