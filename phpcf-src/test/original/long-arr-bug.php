<?php
/**
 * Test for correct formatting of multiline short-syntax array without comma on last member
 */

$view_log_urls['raw'] = [
    'logs-ws' => 'http://' . func()
    . '/url/?class_name=class',
    'logs' => 'http://' . func()
            . '/url/?class_name=class'
];


$arr = [
    'one' => [
        'one',
        'two',
    ],
    'two' => [
        function() { return 1; }
    ]
];