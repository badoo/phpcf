<?php
/**
 * Test for expressions in constant definitions
 */
class One
{
    const TEST = [
        'one', 'two',
        'three'
    ];

    const TEST_FUNC = 1 + PHP_MAJOR_VERSION;

    const TEST_2 = 10 + 10;
    const
        A = [
            'test'
        ],
        B = 10 + 10;
}
