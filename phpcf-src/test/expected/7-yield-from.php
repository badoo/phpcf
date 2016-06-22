<?php
function gen()
{
    yield 1;
    yield 2;
    yield from gen2();
}

class One
{
    public function test()
    {
        yield from test();
    }
}

$a = function() {
    yield from test();
};
