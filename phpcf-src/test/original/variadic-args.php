<?php

function func1( ... $params)
{
    echo count($params);
}

function func2($a,  ... $params)
{
    echo $a . count($params);
}

class C1
{
    public function func1( ... $params)
    {
        echo count($params);
    }

    public function func2($a,  ... $params)
    {
        echo $a . count($params);
    }
}
