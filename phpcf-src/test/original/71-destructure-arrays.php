<?php

$pairs = array(array(1, 2));
while ( list( $a , $b ) = each($pairs)) {
    echo $a + $b;
}

$pairs = [[1, 2]];
while ( [ $a , $b ] = each($pairs)) {
    echo $a + $b;
}
