<?php
/**
 * Class OK
 */
class DocOkClass
{
    public function __construct()
    {
        /** @var int $var - there can be empty strings */

        $var = 5;
    }
}

/**
 * Class with several empty strings after
 */



class DocEmptyClass
{
    public function __construct() {}
}

/**
 * Class on the same line
 */class DocSameLineClass
{
    public function __construct() {}
}

min(4, 5);
class NoDocClassOne
{
    public function __construct() {}
}

min(6, 7);

class NoDocClassTwo
{
    public function __construct() {}
}
