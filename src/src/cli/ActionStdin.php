<?php
/**
 * Format files and re-write them
 * @author Alexander Krasheninnikov <a.krasheninnikov@corp.badoo.com>
 */
namespace Phpcf\Cli;

class ActionStdin extends AbstractAction
{
    protected $description = "format contents from STDIN and outputs the result";

    /**
     * @inheritdoc
     */
    protected function handleInternal(Ctx $Ctx)
    {
        $input = stream_get_contents(STDIN);
        if (false === $input) {
            $this->error("Failed to get content from STDIN");
            return 1;
        }

        $tmpfile = tempnam(sys_get_temp_dir(), "phpcf_stdin");

        if (false === $tmpfile) {
            $this->error("Failed to create tmp file");
            return 1;
        };
        if (file_put_contents($tmpfile, $input) === false) {
            unlink($tmpfile);
            $this->error("Failed to write to {$tmpfile}");
            return 1;
        }
        $FormattingResult = $this->Formatter->formatFile($tmpfile);
        if ($Error = $FormattingResult->getError()) {
            $this->error($Error->getMessage());
            return 1;
        } else {
            readfile($tmpfile);
            return 0;
        }
    }
}
