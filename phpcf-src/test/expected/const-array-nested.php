<?php

const SMTH = [
    'ONE' => [
        'TWO' => Test::TWO,
    ],
];

class Test
{
    const TWO = 2;

    const SMTH = [
        'ONE' => [
            'TWO' => self::TWO,
        ],
    ];
}
