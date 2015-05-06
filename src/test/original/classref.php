<?php

/**
 * Test for correct formatting of class-reference
 */
class ClassName {}


echo ClassName
::
class;

$a = ClassName::class."Empty";

class One
{
    const A = One::
    CLASS;
}

class Ololo
{
    const
        ROFL = One
    ::class;
}