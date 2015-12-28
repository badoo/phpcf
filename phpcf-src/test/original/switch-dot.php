<?php
/**
 * Test for switch, using ';' instead of ':'
 */

switch($a)
{
    case 'a'    ;        $b = 10;
        break;
        default;
                     $a = 5;
break;
}
