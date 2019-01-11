<?php
/**
 * Test for return type declarations
 */

function one():\DateTime {
    date();
}

function two()
:
\DateTime {
    date();
}

function oneEmpty()
:
\DateTime {}

function returnArray()
: array {}

function returnInt()
: int {}

function returnFloat()
: float {}

function returnString()
: float {}

function returnCallable()
: callable {}

class Test {
    function one():\DateTime {
        date();
    }

    function two()
    :
    \DateTime {
        date();
    }

    function oneEmpty()
    : \DateTime {}

    function returnArray()
    : array {}

    function returnInt()
    : int {}

    function returnFloat()
    : float {}

    function returnString()
    : float {}

    function returnCallable()
    : callable {}

    function returnSelf()
    : self{}

    function returnNullable()
    : ? array{}
}

trait TestTrait {
    function one():\DateTime {
        date();
    }

    function two()
    :
    \DateTime {
        date();
    }

    function oneEmpty()
    : \DateTime {}

    function returnArray()
    : array {}

    function returnInt()
    : int {}

    function returnFloat()
    : float {}

    function returnString()
    : float {}

    function returnCallable()
    : callable {}

    function returnSelf()
    : self{}

    function returnNullable()
    : ? array{}
}

interface TestInterface {
    function one():\DateTime;

    function two()
    :
    \DateTime ;

    function oneEmpty()
    : \DateTime;

    function returnArray()
    : array;

    function returnInt()
    : int;

    function returnFloat()
    : float;

    function returnString()
    : float;

    function returnCallable()
    : callable;

    function returnSelf()
    : self;

    function returnNullable()
    : ? array;
}
