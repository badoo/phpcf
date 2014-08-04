<?php
/**
 * Cyrillic conversion table
 * @author Alexander Krasheninnikov <a.krasheninnikov@corp.badoo.com>
 */
namespace Phpcf\Filter;

class StringAsciiMapCyr extends StringAsciiMap
{
    /**
     * @inheritdoc
     */
    protected $convert_map = [
        'а' => 'a',
        'с' => 'c',
        'е' => 'e',
        'о' => 'o',
        'э' => 'e',
        'у' => 'y',
        'А' => 'A',
        'С' => 'C',
        'Е' => 'E',
        'В' => 'B',
        'О' => 'О',
    ];
}
