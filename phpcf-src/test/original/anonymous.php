<?php

$empty_func = function($a){


};

$new_arr = array_map(function($a) {return $a[0];}, $argv);

$callback = function($b){return $b['id'];};

$long_callback = function($c)use ($d,&$e){
	return $c['id'];
};

function doSomething($a,$b) {
	echo 'Hello world!';
}

function canGetFunction(callable $func, $some_param)
{
    echo "here";

    $ShuffleCall = interceptFunctionByCode('shuffle', function (array &$array)  {$t = 7;}, 'd');

    $ShuffleCall = interceptFunctionByCode('shuffle', function (array &$array) {$t = 7;$y = 8;},'d');

    $ShuffleCall = interceptFunctionByCode(
        'shuffle',
        function (array &$array) use($d) { $t = 7; },
        'd'
    );
}

$res = canGetFunction( function() {$a =5;}, $some);

$res2 = canGetFunction(
    function() { $a = 5; },
    $some
);

$res3 = canGetFunction(
    function() {
        $a = 5;
        $b=7;
    },
    $some
);

$res4 = canGetFunction(
    function() { $a = 5; $u= 6; return false;},
    $some
);
