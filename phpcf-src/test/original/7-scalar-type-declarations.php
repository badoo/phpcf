<?php
/**
 * Test for scalar type declarations
 */

$a = function( DateTime $a, int... $vars ){};

function testInt( DateTime $a, int... $vars ) {}
function testFloat(DateTime $a, float... $vars ) {}
function testString(DateTime $a, string... $vars ) {}
function testBool(DateTime $a, bool... $vars ) {}
function testCallable(DateTime $a, callable... $vars ) {}
function testClass(DateTime $a, \DateTime... $vars ) {}

class Test
{
    function testSelf(DateTime $a, self... $vars ) {}
    function testInt(DateTime $a, int... $vars ) {}
    function testFloat(DateTime $a, float... $vars ) {}
    function testString(DateTime $a, string... $vars ) {}
    function testBool(DateTime $a, bool... $vars ) {}
    function testCallable(DateTime $a, callable... $vars ) {}
    function testClass(DateTime $a, \DateTime... $vars ) {}
}

trait TestTrait {
    function testSelf(DateTime $a, self... $vars ) {}
    function testInt(DateTime $a, int... $vars ) {}
    function testFloat(DateTime $a, float... $vars ) {}
    function testString(DateTime $a, string... $vars ) {}
    function testBool(DateTime $a, bool... $vars ) {}
    function testCallable(DateTime $a, callable... $vars ) {}
    function testClass(DateTime $a, \DateTime... $vars ) {}
}

interface TestInterface {
    function testSelf(DateTime $a, self... $vars );
    function testInt(DateTime $a, int... $vars );
    function testFloat(DateTime $a, float... $vars );
    function testString(DateTime $a, string... $vars );
    function testBool(DateTime $a, bool... $vars );
    function testCallable(DateTime $a, callable... $vars );
    function testClass(DateTime $a, \DateTime... $vars );
}