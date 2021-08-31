<?php
/**
 * Test for return type declarations
 */

function one(
    $a, 
    $b
):\DateTime {
    date();
}

function two(
    $a,
    $b
)
:
\DateTime {
    date();
}

function oneEmpty(
    $a,
    $b
)
:
\DateTime {}

function returnArray(
    $a,
    $b
)
: array {}

function returnInt(
    $a,
    $b
)
: int {}

function returnFloat(
    $a,
    $b
)
: float {}

function returnString(
    $a,
    $b
)
: string {}

function returnCallable(
    $a,
    $b
)
: callable {}

class Test {
    function one(
        $a,
        $b
    ):\DateTime {
        date();
    }

    function two(
        $a,
        $b
    )
    :
    \DateTime {
        date();
    }

    function oneEmpty(
        $a,
        $b
    )
    : \DateTime {}

    function returnArray(
        $a,
        $b
    )
    : array {}

    function returnInt(
        $a,
        $b
    )
    : int {}

    function returnFloat(
        $a,
        $b
    )
    : float {}

    function returnString(
        $a,
        $b
    )
    : string {}

    function returnCallable(
        $a,
        $b
    )
    : callable {}

    function returnSelf(
        $a,
        $b
    )
    : self{}
}

trait TestTrait {
    function one(
        $a,
        $b
    ):\DateTime {
        date();
    }

    function two(
        $a,
        $b
    )
    :
    \DateTime {
        date();
    }

    function oneEmpty(
        $a,
        $b
    )
    : \DateTime {}

    function returnArray(
        $a,
        $b
    )
    : array {}

    function returnInt(
        $a,
        $b
    )
    : int {}

    function returnFloat(
        $a,
        $b
    )
    : float {}

    function returnString(
        $a,
        $b
    )
    : string {}

    function returnCallable(
        $a,
        $b
    )
    : callable {}

    function returnSelf(
        $a,
        $b
    )
    : self{}
}

interface TestInterface {
    function one(
        $a,
        $b
    ):\DateTime;

    function two(
        $a,
        $b
    )
    :
    \DateTime ;

    function oneEmpty(
        $a,
        $b
    )
    : \DateTime;

    function returnArray(
        $a,
        $b
    )
    : array;

    function returnInt(
        $a,
        $b
    )
    : int;

    function returnFloat(
        $a,
        $b
    )
    : float;

    function returnString(
        $a,
        $b
    )
    : string;

    function returnCallable(
        $a,
        $b
    )
    : callable;

    function returnSelf(
        $a,
        $b
    )
    : self;
}
