<?php
/**
 * Collector for messages and debug
 * @author Alexander Krasheninnikov <a.krasheninnikov@corp.badoo.com>
 */
namespace Phpcf;

class ExecStat
{
    private $issues = [];

    private $debug = [];

    private $callback_invoked = false;

    private $callback;

    /**
     * @return array
     */
    public function getIssues()
    {
        if ($this->callback && !$this->callback_invoked) {
            call_user_func($this->callback);
        }

        return $this->issues;
    }

    /**
     * @return array
     */
    public function getDebugMessages()
    {
        return $this->debug;
    }

    /**
     * Flush all issues and messages
     */
    public function flush()
    {
        $this->callback_invoked = false;
        $this->debug = $this->issues = [];
    }

    public function addIssue($issue)
    {
        $this->issues[] = $issue;
    }

    public function addDebug($message)
    {
        $this->debug[] = $message;
    }

    /**
     * @param mixed $callback
     * @throws \InvalidArgumentException
     */
    public function setCallback($callback)
    {
        if (!is_callable($callback)) {
            throw new \InvalidArgumentException("Callback is not callable");
        }
        $this->callback = $callback;
    }
}
