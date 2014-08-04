<?php
function shortdef($a, $b, $c) {}

function longdef(
    array $a = array(),
    Something $b,
    OtherThing $c
)
{
    echo "Very nasty formatting for function definition";
}

function longparams(
    $aaaaaaaaaaaaaaa,
    $bbbbbbbbbbbbbbbbbbb,
    $cccccccccccccccccc,
    $ddddddddddddddddddd,
    $eeeeeeeeeeeeeeeeeeee,
    $fffffffffffff
)
{
    echo 'hello';
}

class TestClass
{
    function longparams(
        $aaaaaaaaaaaaaaa,
        $bbbbbbbbbbbbbbbbbbb,
        $cccccccccccccccccc,
        $ddddddddddddddddddd,
        $eeeeeeeeeeeeeeeeeeee,
        $fffffffffffff
    )
    {
        echo 'hello';
    }
}
