<?php
/**
 * Test for empty anon class declaration in any places
 */
$a = new class(10, 20, new \DateTime()) extends \Test {};

$b = new class(10, 20, new \DateTime()) implements \Test {};

function test()
{
    $a = new class(10, 20, new \DateTime()) extends \Test {};

    $b = new class(10, 20, new \DateTime()) implements \Test {};

    if (true) {
        echo 'here';
    }
}

class One
{
    function test()
    {
        $a = new class(10, 20, new \DateTime()) extends \Test {};

        $b = new class(10, 20, new \DateTime()) implements \Test {};
    }
}
