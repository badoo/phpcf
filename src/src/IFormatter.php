<?php
/**
 * Formatter facade
 * @author Alexander Krasheninnikov <a.krasheninnikov@corp.badoo.com>
 */
namespace Phpcf;

interface IFormatter
{
    /**
     * Execute formatting
     * @param string $content
     * @param array $user_lines
     * @return string
     */
    public function format($content, array $user_lines);

    /**
     * Issues from last format execution
     * @return string[]
     */
    public function getIssues();

    /**
     * Install's filter, to be applied to string tokens
     * filter can perform string re-writing and sniff execution message 
     * @param Filter\StringAscii $StringFilter
     */
    public function setStringFilter(\Phpcf\Filter\StringAscii $StringFilter = null);

    /**
     * @param int $width
     * @return void
     */
    public function setMaxLineLength($width);

    /**
     * Install character sequence for tabulation
     * @param string $sequence
     * @return void
     */
    public function setTabSequence($sequence);

    /**
     * Collect messages of badly formatted sequences
     * @param bool $flag
     * @return void
     */
    public function setSniffMessages($flag);

    /**
     * Toggle debug
     * @param bool $flag
     * @return void
     */
    public function setDebugEnabled($flag);
}
