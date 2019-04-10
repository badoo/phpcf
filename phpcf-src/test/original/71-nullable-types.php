<?php
/**
 * Test for nullable return type declarations
 */

function returnFloat(? int $a):  ? float {}

class Test
{
    function returnFloat(? int $a):  ? float {}
}

trait TestTrait
{
    public function returnFloat(
        ? int $a, $b
    ):  ? float {
        return $a + $b;
    }
}

interface TestInterface
{
    function returnFloat(? int $a):  ? float;
}
