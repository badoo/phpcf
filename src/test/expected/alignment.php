<?php

define('QUERY_SOMETHING',    'SELECT * FROM table');
define('QUERY_OTHER_THING',  'SELECT * FROM table');

define('QUERY_SOMETHING', 'SELECT * FROM table');
define('QUERY_OTHER_THING', 'SELECT * FROM table');

$arr = array(
    'some-test'         => true,
    'other-test'        => false,
    'slight-misalign' => 'oh shi!'
);

class Test
{
    public function doSomething() {}

    const QUERY_MULTILINE  = "SELECT
            col1, col2
        FROM
            some_table";

    const OTHER_QUERY      = "INSERT INTO
        table1 (col2, col3)
        VALUES ('#col2#', '#col3#')";

    const MISALIGNED_QUERY = "INSERT INTO
        table1 (col2, col3)
        VALUES ('#col2#', '#col3#')";
}

