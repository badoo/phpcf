<?php
if ($a && $b
    && $c) {
    echo 'd';
}

if($a)break;
elseif($b)do_something_else();
else if($c)define('true', false);
else continue;

if ($a
    and $b)
{

}

class MyTest
{
    public function find(array $filter = array(), array $options = array())
    {
        $local_cache = parent::find($filter, $options);
        if (null !== $local_cache
            and (!isset($options[self::OPTION_RESULT])
                or self::OPTION_RESULT_OBJECT === $options[self::OPTION_RESULT])) {

        }
    }
}
