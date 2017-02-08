<?php

if ($a
    && $b) {
    echo "Hello world!\n";
}

self::doSomething();

call_my_func(MY_CONST);

$this
    ->callOneThing()
    ->callAnotherThing();

$this->callSomething()->callOtherSomething();

array_map(function($arg) { return $arg[1]; }, array('11', '22'));

