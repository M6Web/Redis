<?php

namespace M6Web\Component\Redis\HashFunction\tests\units;

require_once __DIR__ . '/../HashFunction.php';

use M6Web\Component\Redis\tests\units\HashFunction;

class Sha1 extends HashFunction
{
    protected function createHashFunction()
    {
        return new \M6Web\Component\Redis\HashFunction\Sha1();
    }

}
