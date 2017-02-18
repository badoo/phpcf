<?php
/**
 * Test for null-coalescing expressions
 */

$a = $_GET['user']??'one'
    ??'two';

$a = $_GET['user']
    ??
'one';

$a = $_GET['user']                        ??'one'
    ??'two';

$a = $_GET['user'] ?? 'user' ? 'one' : 'two';

$a = $_GET['user'] ?? 'user' ?
        : 'two';

$a = $_GET['user'] 
    ?? 'user' ?: 'two';