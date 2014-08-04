<?php
/**
 * Format files and re-write them
 * @author Alexander Krasheninnikov <a.krasheninnikov@corp.badoo.com>
 */
namespace Phpcf\Cli;

class ActionApply extends AbstractAction
{
    protected $description = "format file, overwrite it and print report";

    /**
     * @inheritdoc
     */
    protected function handleInternal(Ctx $Ctx)
    {
        $has_errors = false;
        foreach ($Ctx->getFiles() as $file) {
            $Result = $this->Formatter->formatFile($file);
            $file = $Result->getFile();
            if ($Error = $Result->getError()) {
                $this->error("$file: " . $Error->getMessage());
            } else {
                if ($Result->wasFormatted()) {
                    if (!file_put_contents($file, $Result->getContent())) {
                        $this->error($file . ': failed to save new content');
                    } else {
                        $this->message("$file formatted successfully");
                    }
                } else {
                    $this->message("$file does not need formatting");
                }
            }
        }
        return $has_errors ? 1 : 0;
    }
}
