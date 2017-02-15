<?php

class A
{
    const
        B = 1;

    const
        ARRAY = 2;

    const
        FUNCTION = 3;

    const
        LIST = 4;

    function foo()
    {
        echo "IN FOO\n";
    }
}

class B
{
    const
        ARRAY = 2,
        FUNCTION = 3,
        LIST = 4;

    function bar()
    {
        echo "IN BAR\n";
    }
}
