<?php
/**
 * Filter, that replace any symbols to their ascii equivalent 
 * @author Alexander Krasheninnikov <a.krasheninnikov@corp.badoo.com>
 */
namespace Phpcf\Filter;

class StringAscii
{
    /** @var array symbols to be replaced */
    private $from = [];
    /** @var array symbols replacement */
    private $to = [];

    private $pattern;

    /**
     * @param StringAsciiMap $Map association holder for replacement
     */
    public function __construct(StringAsciiMap $Map)
    {
        $map = $Map->getMap();
        $this->from = array_keys($map);
        $this->to = array_values($map);
        $this->pattern = '/(' . implode('|', $this->from) . ')+/uU';
    }

    /**gdb
     * @return array
     */
    function __invoke()
    {
        return $this->filter(func_get_arg(0));
    }

    /**
     * Performs asciification of given string, replacing characters according to own config
     * @param $string
     * @return array 0 index - new value, 1 - [sequence => [position, ...]]
     */
    public function filter($string)
    {
        if (empty($this->from) || preg_match('/^([a-zA-Z0-9\_])+$/u', $string)) {
            return [$string, ""];
        }

        $stats = [];

        preg_match_all($this->pattern, $string, $matches, PREG_OFFSET_CAPTURE);
        // collecting stats for symbols
        if (!empty($matches[0])) {
            foreach ($matches[0] as $match) {
                $stats[$match[0]][] = $match[1];
            }
            $new = str_replace($this->from, $this->to, $string, $replace_count);
            $stats = $this->getDescription($string, $stats);
            $string = $new;
        }

        return [$string, $stats];
    }

    /**
     * Format matched results to human readable string
     * @param $source
     * @param array $stats found patterns data
     * @return string
     */
    private function getDescription($source, array $stats)
    {
        if (!empty($stats)) {
            $counter = 0;
            $message = [];
            foreach ($stats as $subject => $offsets) {
                $str = "'" . $subject . "':";
                foreach ($offsets as $offset) {
                    ++$counter;
                    $str .= $offset . ",";
                }
                $message[] = substr($str, 0, -1);
            }
            return "String \"" . $source . "\" contains {$counter} non-ascii symbol(s): " . implode(',', $message);
        }
        return "";
    }
}
