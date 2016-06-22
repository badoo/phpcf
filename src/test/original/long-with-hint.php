<?php
/**
 * Test for array() hint in log param definition
 */

class Test
{
    function log(
        $activation_place = null,
            $groups = 
            array(   ),
        $place = null
    )
    {
        echo "here";
    }
}
