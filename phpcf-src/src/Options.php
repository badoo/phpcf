<?php
/**
 * Options for 
 * @author Alexander Krasheninnikov <a.krasheninnikov@corp.badoo.com>
 */
namespace Phpcf;

class Options
{
    private $tab_sequence = "    ";

    private $max_line_length = 120;

    private $quiet = false;

    private $debug = false;

    private $sniff = false;

    private $emacs_style = false;

    private $summary = false;

    private $custom_styles;

    private $enable_cyrillic_filter = true;

    /**
     * @return bool
     */
    public function isCyrillicFilterEnabled()
    {
        return $this->enable_cyrillic_filter;
    }

    /**
     * @param bool $flag
     * @return $this
     */
    public function toggleCyrillicFilter($flag)
    {
        $this->enable_cyrillic_filter = (bool)$flag;
        return $this;
    }

    /**
     * @return string
     */
    public function getTabSequence()
    {
        return $this->tab_sequence;
    }

    /**
     * @param string $tab_sequence
     * @return $this
     */
    public function setTabSequence($tab_sequence)
    {
        $this->tab_sequence = $tab_sequence;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getMaxLineLength()
    {
        return $this->max_line_length;
    }

    /**
     * @param mixed $max_line_length
     * @return $this
     */
    public function setMaxLineLength($max_line_length)
    {
        $this->max_line_length = $max_line_length;
        return $this;
    }

    public function setQuiet($flag)
    {
        $this->quiet = $flag;
    }

    public function isQuiet()
    {
        return $this->quiet;
    }

    /**
     * @return boolean
     */
    public function isDebug()
    {
        return $this->debug;
    }

    /**
     * @param boolean $debug
     */
    public function setDebug($debug)
    {
        $this->debug = $debug;
    }

    public function toggleSniff($flag)
    {
        $this->sniff = (bool)$flag;
    }

    public function sniffMessages()
    {
        return $this->sniff;
    }

    public function setEmacsStyle($flag)
    {
        $this->emacs_style = (bool)$flag;
    }

    public function isEmacsStyle()
    {
        return $this->emacs_style;
    }

    /**
     * @return boolean
     */
    public function isSummary()
    {
        return $this->summary;
    }

    /**
     * @param boolean $summary
     */
    public function setSummary($summary)
    {
        $this->summary = (bool)$summary;
    }

    /**
     * @return mixed
     */
    public function getCustomStyle()
    {
        return $this->custom_styles;
    }

    /**
     * @param mixed $custom_styles
     */
    public function setCustomStyle($custom_styles)
    {
        $this->custom_styles = $custom_styles;
    }
}
