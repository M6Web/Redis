<?php

namespace M6Web\Component\Redis\tests\units;

use M6Web\Component\Redis\HashFunctionInterface;
use \mageekguy\atoum;

/**
 * Generic tests for classes implementing HashFunctionInterface
 */
abstract class HashFunction extends atoum\test
{
    const ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';

    /**
     * @return HashFunctionInterface
     */
    abstract protected function createHashFunction();

    public function testReturnedValuesAreCorrectlyBounded()
    {
        $hashFunction = $this->createHashFunction();
        $remainingAttempt = 100;

        while ($remainingAttempt-- > 0) {
            $key = str_shuffle(self::ALPHABET);
            $upperBound = rand(1, 10);
            $this
                ->integer($hashFunction->hash($key, $upperBound))
                    ->isGreaterThanOrEqualTo(0)
                    ->isLessThan($upperBound)
            ;
        }
    }

    public function testValuesAreDistributedInRange()
    {
        $hashFunction = $this->createHashFunction();
        $remainingAttempt = 100;
        $nbKeys = 5;
        $collector = array_fill(0, $nbKeys, 0);

        while ($remainingAttempt-- > 0) {
            $key = str_shuffle(self::ALPHABET);
            $collector[$hashFunction->hash($key, $nbKeys)]++;
        }

        // Ideally, we should have 100/5 = 20 hits for each values.
        // Since this test is random, we check at least 1 hit for each.
        foreach ($collector as $nbValues) {
            $this
                ->integer($nbValues)
                    ->isGreaterThan(1);
        }
    }
}
