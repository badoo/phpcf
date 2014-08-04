<?php
class One
{

    public function testOne(
        array
        $one = array(),
        $two,
        array
        $three = [
            array(
                'one' => 'two'
            )
        ]
    )
    {
        echo "Here";
    }

    public function testTwo(
        array
        & $one = array(),
        $two,
        array
        & $three = [
            array(
                'one' => 'two'
            )
        ]
    )
    {
        echo "Here";
    }
}

function testOne(
    array
    $one = array(),
    $two,
    array
    $three =
    array(
        'test' =>
            array(
                'one' => 'two'
            )
    )
)
{
    echo "Test";
}

function testTwo(
    array
    $one = [
        ['one' => 'two',
            'three' => 'four']
    ],
    $two =
    [],
    array
    $three = [
        array(
            'one' => 'two'
        )
    ]
)
{
    echo "Here";
}

