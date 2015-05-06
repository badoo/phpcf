<?php

/**
 * Test formatting for "yield" keyword
 */
function generator() {
    for ($i=0;$i<10;$i++) {        yield
    $i;
    }
}