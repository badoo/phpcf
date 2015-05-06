<?php

if($a &&
    $b) {
    echo "Hello world!\n";
}

self::doSomething();

call_my_func(MY_CONST);

$this
    ->callOneThing()
    ->callAnotherThing();
    
$this->callSomething()->callOtherSomething();

do_something($arg1, $arg2, $arg3);
do_something(
    $arg1
);
do_other_thing($argument1, $argument2, $argument3, $argument1, $argument2, $argument3, $argument1, $argument2, $argument3, $argument1, $argument2, $argument3);

do_other_thing(
    $argument1, $argument2, $argument3,
    $argument1, $argument2, $argument3, $argument1,
    $argument2, $argument3, $argument1, $argument2, $argument3
);

array_map(function($arg) { return $arg[1]; }, array('11', '22'));
