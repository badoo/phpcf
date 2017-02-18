<?php
/**
 * Test for scalar type declarations in long function signatures
 */
$a = function( DateTime $a, 
               int
               ... $vars ){};

function testInt( DateTime $a, 
                  int
                  ... 
                  $vars ) {}

function testFloat( DateTime $a,
                  float
                  ...
                  $vars ) {}

function testString( DateTime $a,
                    string
                    ...
                    $vars ) {}

function testBool( DateTime $a,
                     bool
                     ...
                     $vars ) {}

function testCallable( DateTime $a,
                   callable
                   ...
                   $vars ) {}

function testClass( DateTime $a,
                       \DateTime
                       ...
                       $vars ) {}

class Test
{
    function testInt( DateTime $a,
                      int
                      ...
                      $vars ) {}

    function testFloat( DateTime $a,
                        float
                        ...
                        $vars ) {}

    function testString( DateTime $a,
                         string
                         ...
                         $vars ) {}

    function testBool( DateTime $a,
                       bool
                       ...
                       $vars ) {}
    
    function testCallable( DateTime $a,
                           callable
                           ...
                           $vars ) {}

    function testSelf( DateTime $a,
                           self
                           ...
                           $vars ) {}
    
    function testClass( DateTime $a,
                        \DateTime
                        ...
                        $vars ) {}
}

trait TestTrait {
    function testInt( DateTime $a,
                      int
                      ...
                      $vars ) {}

    function testFloat( DateTime $a,
                        float
                        ...
                        $vars ) {}

    function testString( DateTime $a,
                         string
                         ...
                         $vars ) {}

    function testBool( DateTime $a,
                       bool
                       ...
                       $vars ) {}
    
    function testCallable( DateTime $a,
                           callable
                           ...
                           $vars ) {}
    
    function testSelf( DateTime $a,
                       self
                       ...
                       $vars ) {}
    
    function testClass( DateTime $a,
                        \DateTime
                        ...
                        $vars ) {}
}

interface TestInterface {
    function testInt( DateTime $a,
                      int
                      ...
                      $vars );

    function testFloat( DateTime $a,
                        float
                        ...
                        $vars );

    function testString( DateTime $a,
                         string
                         ...
                         $vars );

    function testBool( DateTime $a,
                       bool
                       ...
                       $vars );

    function testCallable( DateTime $a,
                           callable
                           ...
                           $vars );
    
    function testSelf( DateTime $a,
                       self
                       ...
                       $vars );
    
    function testClass( DateTime $a,
                        \DateTime
                        ...
                        $vars );
}