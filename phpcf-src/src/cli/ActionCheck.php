<?php
/**
 * Checker
 * @author Alexander Krasheninnikov <a.krasheninnikov@corp.badoo.com>
 */
namespace Phpcf\Cli;

class ActionCheck extends AbstractAction
{
    protected $description = "just check a file and report about problems with non-zero exit code";

    /**
     * @inheritdoc
     */
    protected function handleInternal(Ctx $Ctx)
    {
        $has_unformatted = false;
        $has_error = false;
        $Options = $this->Formatter->getOptions();
        $Options->toggleSniff(true);

        foreach ($Ctx->getFiles() as $file) {
            $Result = $this->Formatter->formatFile($file);
            $file = $Result->getFile();
            if ($Error = $Result->getError()) {
                $this->error($file . ":" . $Error->getMessage());
            } else if ($Result->wasFormatted()) {
                $has_unformatted = true;
                $this->printIssues($Result);
            } else if (!$Options->isQuiet()) {
                $this->message($file . ' does not need formatting');
            }
        }
        return $has_error || $has_unformatted ? 1 : 0;
    }
}
