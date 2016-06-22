<?php
/**
 * Retval for Formatter
 * @author Alexander Krasheninnikov <a.krasheninnikov@corp.badoo.com>
 */
namespace Phpcf;

class FormattingResult
{
    /**
     * @var string
     */
    private $content;

    /**
     * @var float
     */
    private $time_exec;

    /**
     * @var \Exception
     */
    private $Error;

    /**
     * @var string
     */
    private $file;

    /**
     * Explain of file issues
     * @var array
     */
    private $issues = [];

    /**
     * @var bool
     */
    private $was_formatted;

    /**
     * @param string $file
     */
    public function __construct($file)
    {
        $this->file = $file;
    }

    /**
     * @return \Exception
     */
    public function getError()
    {
        return $this->Error;
    }

    /**
     * @param \Exception $Error
     */
    public function setError($Error)
    {
        $this->Error = $Error;
    }

    /**
     * @return mixed
     */
    public function getTimeExec()
    {
        return $this->time_exec;
    }

    /**
     * @param mixed $time_exec
     */
    public function setTimeExec($time_exec)
    {
        $this->time_exec = $time_exec;
    }

    /**
     * @return mixed
     */
    public function getContent()
    {
        return $this->content;
    }

    /**
     * @param mixed $content
     */
    public function setContent($content)
    {
        $this->content = $content;
    }

    /**
     * @return string
     */
    public function __toString()
    {
        if ($this->Error) {
            return $this->Error->getMessage();
        }
        return $this->getContent();
    }

    public function setWasFormatted($flag)
    {
        $this->was_formatted = $flag;
    }

    public function wasFormatted()
    {
        return $this->was_formatted;
    }

    public function getIssues()
    {
        return $this->issues;
    }

    public function setIssues(array $issues)
    {
        $this->issues = $issues;
    }

    public function getFile()
    {
        return $this->file;
    }
}
