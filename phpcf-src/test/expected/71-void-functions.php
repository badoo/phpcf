<?php
/**
 * Test for void functions declarations
 */

function returnFloat() : void {}

class Test
{
    function returnFloat() : void {}
}

trait TestTrait
{
    public function returnFloat(
        $a,
        $b
    ) : void
    {
        echo $a + $b;
        return;
    }
}

interface TestInterface
{
    function returnFloat() : void;
}
