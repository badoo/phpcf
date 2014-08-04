<?php
/*
 * Test for function name formatting (brace right after name)
 */

function name() {}

name();

name();

function name_two() {}

class One
{
    function one() {}

    function two() {}
}

$one->call();

$one
    ->call();

$one->call();

$one
    ->call();
