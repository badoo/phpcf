<?php
/**
 * Base class for string-ascification table prividers
 * @author Alexander Krasheninnikov <a.krasheninnikov@corp.badoo.com>
 */
namespace Phpcf\Filter;

abstract class StringAsciiMap
{
    /**
     * @var array replace table, key => character to replace, value => ascii character
     */
    protected $convert_map = [];

    /**
     * Return association for replacement
     * @return array key => sequence to replace from, value => destination sequence
     */
    public function getMap()
    {
        return $this->convert_map;
    }
}
