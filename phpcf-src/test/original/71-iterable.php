<?php

function testIterable( iterable $A ,  iterable ...$B)  :  iterable {
    echo "Something"; return $B[0];
}
