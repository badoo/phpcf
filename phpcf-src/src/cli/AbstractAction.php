<?php
/**
 * Base class for actions
 * @author Alexander Krasheninnikov <a.krasheninnikov@corp.badoo.com>
 */
namespace Phpcf\Cli;

abstract class AbstractAction implements IAction
{
    /**
     * @var \Phpcf\Formatter
     */
    protected $Formatter;

    protected $description;

    const MAX_LINES = 15;

    /**
     * @inheritdoc
     */
    public function handle(\Phpcf\Formatter $Formatter, Ctx $Ctx)
    {
        $this->Formatter = $Formatter;
        return $this->handleInternal($Ctx);
    }

    /**
     * @param Ctx $Ctx
     * @return int
     */
    protected abstract function handleInternal(Ctx $Ctx);

    /**
     * Print message to stdout
     * @param string $msg
     */
    protected function message($msg)
    {
        fwrite(STDOUT, $msg . PHP_EOL);
    }

    /**
     * Print message to stderr
     * @param string $error
     */
    protected function error($error)
    {
        fwrite(STDOUT, $error . PHP_EOL);
    }

    /**
     * Description, what this action do
     * @return string
     */
    public function getDescription()
    {
        return $this->description;
    }

    /**
     * @param \Phpcf\FormattingResult $Result
     */
    protected function printIssues(\Phpcf\FormattingResult $Result)
    {
        $file = $Result->getFile();
        $emacs = $this->Formatter->getOptions()->isEmacsStyle();
        $is_debug = $this->Formatter->getOptions()->isDebug();

        if ($issues = $Result->getIssues()) {
            if (!$emacs) $this->message("$file issues:");
            if ($this->Formatter->getOptions()->isSummary()) {
                $this->message("Total errors: " . count($issues));
            } else {
                $i = 0;
                foreach ($issues as $issue) {
                    $i++;
                    if ($emacs && $abs = realpath($Result->getFile())) {
                        $issue .= " [$abs]";
                    }
                    $this->message("    {$issue}");

                    if (!$is_debug && $i >= self::MAX_LINES && ($cnt = (count($issues) - $i - 1)) > 1) {
                        $this->message("    ... have $cnt more messages, not shown");
                        break;
                    }
                }
                if (!$emacs) $this->message("");
            }
        }
    }
}
