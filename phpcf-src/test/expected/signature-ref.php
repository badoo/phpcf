<?php
/**
 * Test for passing value as reference
 */

function (DateTime &$Date, &$ref) {
    //anon func
};

function test1(DateTime &$Date, &$ref) {
    // named function
}

function test(
    DateTime &$Date,
    &$ref,
    $a,
    $b,
    $c
) {
    // long named function
}

class Test
{
    public function test(DateTime &$Date, &$ref) {
        // class method
    }

    public function testLong(
        DateTime &$Date,
        &$ref,
        $a,
        $b,
        $c
    ) {
        // long method
    }
}
