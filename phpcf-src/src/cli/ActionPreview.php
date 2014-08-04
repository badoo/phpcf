<?php
/**
 * Preview for formatting
 * @author Alexander Krasheninnikov <a.krasheninnikov@corp.badoo.com>
 */
namespace Phpcf\Cli;

class ActionPreview extends AbstractAction
{
    /**
     * @var string
     */
    private $diff_cmd = "diff -u";

    /**
     * @var string
     */
    private $cdiff_cmd;

    protected $description = "show diff between original and suggested format and print report";

    /**
     * @param mixed $cdiff_cmd
     */
    public function setCdiffCmd($cdiff_cmd)
    {
        $this->cdiff_cmd = $cdiff_cmd;
    }

    /**
     * @inheritdoc
     */
    protected function handleInternal(Ctx $Ctx)
    {
        $this->Formatter->getOptions()->toggleSniff(true);

        $has_unformatted = false;
        $has_error = false;
        foreach ($Ctx->getFiles() as $file_spec) {
            $file = $file_spec;
            if (strpos($file_spec, ':') !== false) {
                list($file,) = explode(':', $file);
            }

            $initial = file_get_contents($file);
            if (false === $initial) {
                $this->error($file . ": Failed to get content");
            } else {
                $Result = $this->Formatter->formatFile($file_spec);
                if ($Error = $Result->getError()) {
                    $this->error($file . ":" . $Error->getMessage());
                } else {
                    if ($Result->wasFormatted()) {
                        $this->printIssues($Result);

                        $tmp = tempnam(sys_get_temp_dir(), "phpcf");
                        if (false === $tmp) {
                            $this->error($file . ': failed to create tmp file for diff');
                            continue;
                        }
                        if (false === (file_put_contents($tmp, $Result->getContent()))) {
                            $this->error($file . ': failed to write content to tmp');
                            unlink($tmp);
                            continue;
                        }
                        $cmd = $this->diff_cmd . " " . escapeshellarg($file) . " " . escapeshellarg($tmp);
                        if (null !== $this->cdiff_cmd) {
                            $cmd .= " | " . $this->cdiff_cmd;
                        }
                        system($cmd);
                        unlink($tmp);
                    } else {
                        $this->message("$file: is OK");
                    }
                }
            }
        }
        return $has_error || $has_unformatted ? 1 : 0;
    }

    /**
     * @return ActionPreview
     */
    public static function create()
    {
        $retval = new self();
        if (\Phpcf\Helper::isAtty()) {
            // @todo eliminate OS-specific
            $cdiff = '/usr/bin/cdiff';
            if (is_executable($cdiff)) {
                $retval->setCdiffCmd($cdiff);
            }
        }
        return $retval;
    }
}
