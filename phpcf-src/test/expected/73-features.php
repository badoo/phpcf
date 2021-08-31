<?php

$array = [1, 2];
list($a, &$b) = $array;

$parts = explode(
    ",",
    implode(
        ",",
        ["a", "b", "c"],
    ),
);

function h($cnt)
{
    return str_repeat(
        <<<HERE
    123 {$cnt}
    HERE
            . chr(123),
        intval($cnt)
    );
}

function n($cnt)
{
    return str_repeat(
        <<<'NOW'
    123 {$cnt}
    NOW
            . chr(123),
        intval($cnt)
    );
}

echo 'end';
