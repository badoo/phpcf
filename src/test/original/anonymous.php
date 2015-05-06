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
